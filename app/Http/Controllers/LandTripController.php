<?php

namespace App\Http\Controllers;

use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Http\Requests\LandTrips\StoreLandTripRequest;
use App\Http\Requests\LandTrips\SyncLandTripCarsRequest;
use App\Http\Requests\LandTrips\TransitionLandTripRequest;
use App\Http\Requests\LandTrips\UpdateLandTripRequest;
use App\Models\LandTrip;
use App\Services\CompanyService;
use App\Services\CountryService;
use App\Services\LandTripPostingService;
use App\Services\LandTripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LandTripController extends Controller
{
    public function __construct(
        private readonly LandTripService $landTripService,
        private readonly LandTripPostingService $postingService,
        private readonly CountryService $countryService,
        private readonly CompanyService $companyService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'company_id' => $request->string('company_id')->toString(),
        ];

        $trips = $this->landTripService
            ->paginate($filters)
            ->through(fn (LandTrip $trip) => $this->landTripService->transform($trip));

        return Inertia::render('LandTrips/Index', [
            'trips' => $trips,
            'filters' => $filters,
            'companies' => $this->companyService->options(),
            'statuses' => $this->statusOptions(),
            'canManage' => $request->user()?->can(Permission::LandTripsManage->value) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', LandTrip::class);

        $voyageId = $request->integer('voyage_id') ?: null;

        return Inertia::render('LandTrips/Create', [
            'countries' => $this->countryService->activeOptions(),
            'companies' => $this->companyService->options(),
            'voyages' => $this->landTripService->voyageOptions(),
            'voyageCars' => $this->landTripService->voyageCarOptions($voyageId),
            'selectedVoyageId' => $voyageId,
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

        $this->landTripService->delete($land_trip, $request->user());

        return redirect()
            ->route('land-trips.index')
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
