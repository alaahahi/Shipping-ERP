<?php

namespace App\Http\Controllers;

use App\Enums\DubaiEntryKind;
use App\Http\Requests\DubaiAccounts\ImportDubaiCarsRequest;
use App\Http\Requests\DubaiAccounts\ImportDubaiSoaRequest;
use App\Http\Requests\DubaiAccounts\StoreDubaiEntryRequest;
use App\Http\Requests\DubaiAccounts\StoreDubaiPartnerRequest;
use App\Http\Requests\DubaiAccounts\UpdateDubaiPartnerRequest;
use App\Models\DubaiAccountEntry;
use App\Models\DubaiPartner;
use App\Services\DubaiAccountService;
use App\Services\DubaiCarExcelImportService;
use App\Services\DubaiSoaExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DubaiAccountController extends Controller
{
    public function __construct(
        private readonly DubaiAccountService $dubaiAccountService,
        private readonly DubaiSoaExcelImportService $soaExcelImportService,
        private readonly DubaiCarExcelImportService $carExcelImportService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DubaiPartner::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'active' => $request->string('active')->toString(),
        ];

        $partners = $this->dubaiAccountService
            ->paginatePartners($filters)
            ->through(fn (DubaiPartner $partner) => $this->dubaiAccountService->transformPartner($partner));

        return Inertia::render('DubaiAccounts/Index', [
            'partners' => $partners,
            'filters' => $filters,
            'canManage' => $request->user()?->can('dubai_accounts.manage') ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', DubaiPartner::class);

        return Inertia::render('DubaiAccounts/Create');
    }

    public function store(StoreDubaiPartnerRequest $request): RedirectResponse
    {
        Gate::authorize('create', DubaiPartner::class);

        $partner = $this->dubaiAccountService->createPartner($request->validated());

        return redirect()
            ->route('dubai-accounts.show', $partner)
            ->with('success', "Dubai partner «{$partner->name}» created.");
    }

    public function show(Request $request, DubaiPartner $dubai_account): Response
    {
        Gate::authorize('view', $dubai_account);

        $selectedEntryId = $request->integer('entry') ?: null;
        $selectedEntry = null;
        $cars = [];
        $importPreview = null;

        if ($selectedEntryId) {
            $selectedEntry = DubaiAccountEntry::query()
                ->where('dubai_partner_id', $dubai_account->id)
                ->whereKey($selectedEntryId)
                ->first();

            if ($selectedEntry) {
                $cars = $selectedEntry->cars()
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($car) => [
                        'id' => $car->id,
                        'chassis_no' => $car->chassis_no,
                        'consignee_name' => $car->consignee_name,
                        'shipper_name' => $car->shipper_name,
                        'description' => $car->description,
                        'weight' => $car->weight,
                        'code' => $car->code,
                    ]);

                if ($selectedEntry->excel_file_path) {
                    try {
                        $importPreview = $this->carExcelImportService->preview($selectedEntry);
                    } catch (\Throwable) {
                        $importPreview = null;
                    }
                }
            }
        }

        return Inertia::render('DubaiAccounts/Show', [
            'partner' => $this->dubaiAccountService->transformPartner($dubai_account),
            'ledger' => $this->dubaiAccountService->statement($dubai_account),
            'entryKinds' => collect(DubaiEntryKind::cases())->map(fn (DubaiEntryKind $kind) => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ])->values(),
            'selectedEntry' => $selectedEntry ? [
                'id' => $selectedEntry->id,
                'doc_no' => $selectedEntry->doc_no,
                'entry_kind' => $selectedEntry->entry_kind?->value,
                'can_import_cars' => $selectedEntry->entry_kind === DubaiEntryKind::Shipment,
                'excel_original_name' => $selectedEntry->excel_original_name,
            ] : null,
            'cars' => $cars,
            'importPreview' => $importPreview,
            'canManage' => $request->user()?->can('dubai_accounts.manage') ?? false,
        ]);
    }

    public function edit(DubaiPartner $dubai_account): Response
    {
        Gate::authorize('update', $dubai_account);

        return Inertia::render('DubaiAccounts/Edit', [
            'partner' => $this->dubaiAccountService->transformPartner($dubai_account, false),
        ]);
    }

    public function update(UpdateDubaiPartnerRequest $request, DubaiPartner $dubai_account): RedirectResponse
    {
        Gate::authorize('update', $dubai_account);

        $partner = $this->dubaiAccountService->updatePartner($dubai_account, $request->validated());

        return redirect()
            ->route('dubai-accounts.show', $partner)
            ->with('success', "Dubai partner «{$partner->name}» updated.");
    }

    public function destroy(DubaiPartner $dubai_account): RedirectResponse
    {
        Gate::authorize('delete', $dubai_account);

        $name = $dubai_account->name;
        $this->dubaiAccountService->deletePartner($dubai_account);

        return redirect()
            ->route('dubai-accounts.index')
            ->with('success', "Dubai partner «{$name}» deleted.");
    }

    public function storeEntry(StoreDubaiEntryRequest $request, DubaiPartner $dubai_account): RedirectResponse
    {
        Gate::authorize('update', $dubai_account);

        $this->dubaiAccountService->createEntry($dubai_account, $request->validated());

        return redirect()
            ->route('dubai-accounts.show', $dubai_account)
            ->with('success', 'Entry added to Dubai statement.');
    }

    public function destroyEntry(DubaiPartner $dubai_account, DubaiAccountEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $dubai_account);
        abort_unless($entry->dubai_partner_id === $dubai_account->id, 404);

        $this->dubaiAccountService->deleteEntry($entry);

        return redirect()
            ->route('dubai-accounts.show', $dubai_account)
            ->with('success', 'Entry deleted.');
    }

    public function importSoa(ImportDubaiSoaRequest $request, DubaiPartner $dubai_account): RedirectResponse
    {
        Gate::authorize('update', $dubai_account);

        $result = $this->soaExcelImportService->import(
            $dubai_account,
            $request->file('file'),
            $request->boolean('replace')
        );

        $message = "SOA import: {$result['imported']} imported, {$result['skipped']} skipped.";
        if ($result['errors'] !== []) {
            $message .= ' Some rows failed.';
        }

        return redirect()
            ->route('dubai-accounts.show', $dubai_account)
            ->with('success', $message);
    }

    public function previewCars(ImportDubaiCarsRequest $request, DubaiPartner $dubai_account, DubaiAccountEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $dubai_account);
        abort_unless($entry->dubai_partner_id === $dubai_account->id, 404);

        $this->carExcelImportService->storeUpload($entry, $request->file('file'));

        return redirect()
            ->route('dubai-accounts.show', ['dubai_account' => $dubai_account, 'entry' => $entry->id])
            ->with('success', 'Car Excel uploaded. Review preview then confirm import.');
    }

    public function confirmCars(Request $request, DubaiPartner $dubai_account, DubaiAccountEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $dubai_account);
        abort_unless($entry->dubai_partner_id === $dubai_account->id, 404);

        $result = $this->carExcelImportService->importFromStoredPath($entry);

        return redirect()
            ->route('dubai-accounts.show', ['dubai_account' => $dubai_account, 'entry' => $entry->id])
            ->with(
                'success',
                "Cars import: {$result['imported']} imported, {$result['duplicates']} duplicates, {$result['skipped']} skipped."
            );
    }
}
