<?php

namespace App\Http\Controllers;

use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Http\Requests\LandTrips\BulkDeleteCompanyLandCarsRequest;
use App\Http\Requests\LandTrips\BulkUpdateCompanyLandCarPriceRequest;
use App\Http\Requests\LandTrips\BulkUpdateCompanyLandCarStatusRequest;
use App\Http\Requests\LandTrips\CompanyLandCarsOutputRequest;
use App\Http\Requests\LandTrips\DestroyCompanyCmrFileRequest;
use App\Http\Requests\LandTrips\RenameCompanyCmrGroupRequest;
use App\Http\Requests\LandTrips\StoreCompanyCmrFileRequest;
use App\Http\Requests\LandTrips\StoreLandTripRequest;
use App\Http\Requests\LandTrips\SyncCompanyLandCarsRequest;
use App\Http\Requests\LandTrips\SyncLandTripCarsRequest;
use App\Http\Requests\LandTrips\TransferCompanyLandCarsRequest;
use App\Http\Requests\LandTrips\TransitionLandTripRequest;
use App\Http\Requests\LandTrips\UpdateCompanyLandCarDetailsRequest;
use App\Http\Requests\LandTrips\UpdateCompanyLandCarPriceRequest;
use App\Http\Requests\LandTrips\UpdateCompanyLandCarRequest;
use App\Http\Requests\LandTrips\UpdateCompanyLandManifestRequest;
use App\Http\Requests\LandTrips\UpdateLandTripRequest;
use App\Models\Company;
use App\Models\LandCompanyCmrFile;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Services\CompanyService;
use App\Services\CompanyWalletService;
use App\Services\CountryService;
use App\Services\LandTripCarDeletionLogService;
use App\Services\LandTripCarImportLogService;
use App\Services\LandTripCarLocationChangeService;
use App\Services\LandTripCarPriceChangeService;
use App\Services\LandTripExcelImportService;
use App\Services\LandTripPostingService;
use App\Services\LandTripService;
use App\Support\ApplicationTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandTripController extends Controller
{
    public function __construct(
        private readonly LandTripService $landTripService,
        private readonly LandTripPostingService $postingService,
        private readonly LandTripExcelImportService $importService,
        private readonly CountryService $countryService,
        private readonly CompanyService $companyService,
        private readonly LandTripCarLocationChangeService $locationChangeService,
        private readonly LandTripCarImportLogService $importLogService,
        private readonly LandTripCarPriceChangeService $priceChangeService,
        private readonly LandTripCarDeletionLogService $deletionLogService,
        private readonly CompanyWalletService $walletService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        $companies = $this->landTripService
            ->paginateCompanies();

        $chassisMatches = $this->landTripService->matchedCarsByChassis(
            collect($companies->items())->pluck('id')->all(),
            null
        );

        $companies->through(function (Company $company) use ($chassisMatches) {
            $company->matched_car = $chassisMatches[$company->id] ?? null;

            return $this->landTripService->transformCompanyHub($company);
        });

        return Inertia::render('LandTrips/Index', [
            'companies' => $companies,
            'filters' => ['search' => ''],
            'canManage' => $request->user()?->can(Permission::LandTripsManage->value) ?? false,
        ]);
    }

    public function searchCompanies(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        $search = trim($request->string('search')->toString());
        if ($search === '') {
            return response()->json(['companies' => []]);
        }

        $companies = $this->landTripService->paginateCompanies(['search' => $search], 40);
        $chassisMatches = $this->landTripService->matchedCarsByChassis(
            collect($companies->items())->pluck('id')->all(),
            $search
        );

        $items = collect($companies->items())->map(function (Company $company) use ($chassisMatches) {
            $company->matched_car = $chassisMatches[$company->id] ?? null;

            return $this->landTripService->transformCompanyHub($company);
        })->values();

        return response()->json(['companies' => $items]);
    }

    public function showCompany(Request $request, Company $company): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        $filters = $this->landTripService->resolveCompanyCarFilters($company, [
            'search' => trim($request->string('search')->toString()),
            'location_status_id' => $request->string('location_status_id')->toString(),
            'highlight_car_id' => $request->integer('highlight') ?: null,
            'sort' => $this->landTripService->normalizeCompanyCarSort($request->string('sort')->toString()),
        ]);

        $user = $request->user();

        $cars = $this->landTripService
            ->paginateCompanyCars($company, $filters, LandTripService::COMPANY_CARS_PER_PAGE, 1)
            ->through(fn ($car) => $this->landTripService->transformCar($car));

        return Inertia::render('LandTrips/Company', [
            'company' => $this->landTripService->transformCompanyHub($company->loadCount('landTrips')),
            'cars' => $cars,
            'statusSummary' => $this->landTripService->companyStatusSummary($company),
            'carStatuses' => $this->landTripService->carStatusOptions(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'location_status_id' => $filters['location_status_id'],
                'sort' => $this->landTripService->normalizeCompanyCarSort($filters['sort'] ?? null),
            ],
            'highlightCarId' => $filters['highlight_car_id'],
            'canManage' => $user?->can(Permission::LandTripsManage->value) ?? false,
            'locationLog' => $this->locationChangeService->meta($company),
            'importLog' => $this->importLogService->meta($company),
            'deletionLog' => $this->deletionLogService->meta($company),
            'priceLog' => ['has_entries' => $this->priceChangeService->hasEntriesForCompany($company)],
            'wallet' => $this->walletService->payload($company),
            'chassisLetterOCount' => $this->landTripService->countChassisLetterO($company),
        ]);
    }

    public function companyCars(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'location_status_id' => $request->string('location_status_id')->toString(),
            'sort' => $this->landTripService->normalizeCompanyCarSort($request->string('sort')->toString()),
        ];

        $cars = $this->landTripService
            ->paginateCompanyCars($company, $filters, LandTripService::COMPANY_CARS_PER_PAGE)
            ->through(fn ($car) => $this->landTripService->transformCar($car));

        return response()->json($cars);
    }

    public function companyCarCheck(Company $company): JsonResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        return response()->json($this->landTripService->carCheckIndex($company));
    }

    public function syncCompanyCars(SyncCompanyLandCarsRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->landTripService->addCompanyCars($company, $request->validated('cars') ?? [], $request->user());

        return back()->with('success', 'Cars saved.');
    }

    public function bulkUpdateCompanyCarStatus(BulkUpdateCompanyLandCarStatusRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $updated = $this->landTripService->bulkUpdateCompanyCarLocations(
            $company,
            $request->validated(),
            $request->user()
        );

        return back()->with('success', "Updated {$updated} cars.");
    }

    public function destroyCompanyCars(BulkDeleteCompanyLandCarsRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $deleted = $this->landTripService->deleteCompanyCars(
            $company,
            $request->validated('car_ids'),
            $request->user()
        );

        return back()->with('success', "Deleted {$deleted} cars.");
    }

    public function transferCompanyCars(TransferCompanyLandCarsRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $toCompany = Company::query()->findOrFail($request->integer('to_company_id'));
        $moved = $this->landTripService->transferCompanyCars(
            $company,
            $toCompany,
            $request->validated('car_ids'),
            $request->user(),
            $request->validated('notes')
        );

        return back()->with('success', "Transferred {$moved} cars.");
    }

    public function updateCompanyCarPrice(UpdateCompanyLandCarPriceRequest $request, Company $company, LandTripCar $car): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->landTripService->updateCompanyCarPrice(
            $company,
            $car,
            (float) $request->validated('price'),
            $request->user()
        );

        return back();
    }

    public function bulkUpdateCompanyCarPrice(BulkUpdateCompanyLandCarPriceRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $updated = $this->landTripService->bulkUpdateCompanyCarPrices(
            $company,
            $request->validated('car_ids'),
            (float) $request->validated('price'),
            $request->user()
        );

        return back()->with('success', "Updated {$updated} cars.");
    }

    public function updateCompanyCarDetails(UpdateCompanyLandCarDetailsRequest $request, Company $company, LandTripCar $car): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->landTripService->updateCompanyCarDetails(
            $company,
            $car,
            $request->validated(),
            $request->user()
        );

        return back();
    }

    public function updateCompanyCar(UpdateCompanyLandCarRequest $request, Company $company, LandTripCar $car): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->landTripService->updateCompanyCar(
            $company,
            $car,
            $request->validated(),
            $request->user()
        );

        return back()->with('success', 'Car updated.');
    }

    public function companyCarDuplicates(Company $company): JsonResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        return response()->json([
            'groups' => $this->landTripService->companyChassisDuplicates($company),
        ]);
    }

    public function companyCmrGroups(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        return response()->json([
            'groups' => $this->landTripService->companyCmrGroups($company, [
                'search' => $request->string('search')->toString(),
                'location_status_id' => $request->string('location_status_id')->toString(),
            ]),
        ]);
    }

    public function companyModelGroups(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        return response()->json([
            'groups' => $this->landTripService->companyModelGroups($company, [
                'search' => $request->string('search')->toString(),
                'location_status_id' => $request->string('location_status_id')->toString(),
            ]),
        ]);
    }

    public function renameCompanyCmrGroup(RenameCompanyCmrGroupRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('create', LandTrip::class);

        $result = $this->landTripService->renameCompanyCmrGroup(
            $company,
            $request->validated('from_cmr_key'),
            $request->validated('to_cmr_key'),
            $request->user()
        );

        return response()->json($result);
    }

    public function storeCompanyCmrFile(StoreCompanyCmrFileRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('create', LandTrip::class);

        $file = $this->landTripService->storeCompanyCmrFile(
            $company,
            $request->validated('cmr_key'),
            $request->file('file'),
            $request->user()
        );

        return response()->json([
            'cmr_key' => $file->cmr_key,
            'attachment' => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'url' => $file->publicUrl(),
            ],
        ]);
    }

    public function showCompanyCmrFile(Company $company, LandCompanyCmrFile $cmrFile): StreamedResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        if ((int) $cmrFile->company_id !== (int) $company->id) {
            abort(404);
        }

        if (! $cmrFile->attachment_path || ! Storage::disk('public')->exists($cmrFile->attachment_path)) {
            abort(404);
        }

        $downloadName = $cmrFile->original_name ?: basename($cmrFile->attachment_path);

        return Storage::disk('public')->response(
            $cmrFile->attachment_path,
            $downloadName,
            [
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            ]
        );
    }

    public function destroyCompanyCmrFile(DestroyCompanyCmrFileRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->landTripService->destroyCompanyCmrFile(
            $company,
            $request->validated('cmr_key'),
            $request->user()
        );

        return response()->json(['ok' => true]);
    }

    public function updateCompanyManifest(UpdateCompanyLandManifestRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->landTripService->updateCompanyManifestMeta($company, $request->validated(), $request->user());

        return back()->with('success', 'Manifest details saved.');
    }

    public function companyImport(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $trip = $this->landTripService->workingTripForCompany($company, $request->user());

        return redirect()->route('land-trips.import', $trip);
    }

    public function exportCompany(CompanyLandCarsOutputRequest $request, Company $company): StreamedResponse
    {
        return $this->importService->exportCompanyCars($company, $request->filters());
    }

    public function exportCompanyPdf(CompanyLandCarsOutputRequest $request, Company $company): HttpResponse
    {
        return $this->importService->exportCompanyCarsPdf($company, $request->filters());
    }

    public function printCompany(CompanyLandCarsOutputRequest $request, Company $company): Response
    {
        return Inertia::render('LandTrips/CompanyCarsPrint', [
            ...$this->landTripService->companyCarsPrintPayload($company, $request->filters()),
            'printedAt' => ApplicationTimezone::formatNowLabel(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', LandTrip::class);

        $voyageId = $request->integer('voyage_id') ?: null;
        $companyId = $request->integer('company_id') ?: null;

        return Inertia::render('LandTrips/Create', [
            'countries' => $this->countryService->activeOptions(),
            'companies' => $this->companyService->options(),
            'voyages' => $this->landTripService->voyageOptions(),
            'voyageCars' => $this->landTripService->voyageCarOptions($voyageId),
            'selectedVoyageId' => $voyageId,
            'selectedCompanyId' => $companyId,
            'carStatuses' => $this->landTripService->carStatusOptions(),
            'companyLocked' => $companyId !== null,
        ]);
    }

    public function store(StoreLandTripRequest $request): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $trip = $this->landTripService->create($request->validated(), $request->user());

        return redirect()
            ->route('land-trips.show', $trip)
            ->with('success', "Land trip CMR {$trip->cmr_number} created.");
    }

    public function show(LandTrip $land_trip): Response
    {
        Gate::authorize('view', $land_trip);

        $user = request()->user();

        return Inertia::render('LandTrips/Show', [
            'trip' => $this->landTripService->transform($land_trip, detailed: true),
            'voyageCars' => $this->landTripService->voyageCarOptions($land_trip->voyage_id),
            'carStatuses' => $this->landTripService->carStatusOptions(),
            'transitions' => collect($land_trip->status->allowedTransitions())
                ->map(fn (LandTripStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
            'canManage' => $user?->can(Permission::LandTripsManage->value) ?? false,
            'canPost' => $user?->can('post', $land_trip) ?? false,
        ]);
    }

    public function edit(Request $request, LandTrip $land_trip): Response
    {
        Gate::authorize('update', $land_trip);

        $voyageId = $request->integer('voyage_id') ?: $land_trip->voyage_id;

        return Inertia::render('LandTrips/Edit', [
            'trip' => $this->landTripService->transform($land_trip, detailed: true),
            'countries' => $this->countryService->activeOptions(),
            'companies' => $this->companyService->options(),
            'voyages' => $this->landTripService->voyageOptions(),
            'voyageCars' => $this->landTripService->voyageCarOptions($voyageId),
            'carStatuses' => $this->landTripService->carStatusOptions(),
        ]);
    }

    public function update(UpdateLandTripRequest $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('update', $land_trip);

        $this->landTripService->update($land_trip, $request->validated());

        return redirect()
            ->route('land-trips.show', $land_trip)
            ->with('success', 'Land trip updated.');
    }

    public function destroy(Request $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('delete', $land_trip);

        $companyId = $land_trip->company_id;
        $this->landTripService->delete($land_trip, $request->user());

        return redirect()
            ->route('land-trips.companies.show', $companyId)
            ->with('success', 'Land trip deleted.');
    }

    public function syncCars(SyncLandTripCarsRequest $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('update', $land_trip);

        $this->landTripService->syncCars($land_trip, $request->validated('cars') ?? []);

        return back()->with('success', 'Cars saved.');
    }

    public function transition(TransitionLandTripRequest $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('transition', $land_trip);

        $status = LandTripStatus::from($request->validated('status'));
        $this->landTripService->transition($land_trip, $status);

        return back()->with('success', "Status changed to {$status->label()}.");
    }

    public function post(Request $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('post', $land_trip);

        $this->postingService->post($land_trip, $request->user());

        return back()->with('success', 'Land trip freight posted to accounting.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(LandTripStatus::cases())
            ->map(fn (LandTripStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }
}
