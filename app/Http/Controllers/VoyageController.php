<?php

namespace App\Http\Controllers;

use App\Domain\DefaultPorts;
use App\Enums\Currency;
use App\Enums\VoyageExpenseType;
use App\Enums\VoyageStatus;
use App\Http\Requests\Voyages\StoreVoyageRequest;
use App\Http\Requests\Voyages\TransitionVoyageRequest;
use App\Http\Requests\Voyages\UpdateVoyageRequest;
use App\Http\Requests\Voyages\StoreWaypointRequest;
use App\Models\Voyage;
use App\Services\ShipService;
use App\Services\CompanyLedgerService;
use App\Services\VoyageCarService;
use App\Services\VoyageCompanyService;
use App\Services\VoyageExpensePostingService;
use App\Services\VoyageExpenseService;
use App\Services\VoyageService;
use App\Services\VoyageSettlementPostingService;
use App\Services\VoyageSettlementService;
use App\Services\VoyageTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VoyageController extends Controller
{
    public function __construct(
        private readonly VoyageService $voyageService,
        private readonly ShipService $shipService,
        private readonly VoyageCompanyService $voyageCompanyService,
        private readonly VoyageCarService $voyageCarService,
        private readonly VoyageExpenseService $voyageExpenseService,
        private readonly VoyageExpensePostingService $voyageExpensePostingService,
        private readonly VoyageSettlementService $voyageSettlementService,
        private readonly VoyageSettlementPostingService $voyageSettlementPostingService,
        private readonly CompanyLedgerService $companyLedgerService,
        private readonly VoyageTrackingService $voyageTrackingService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Voyage::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'ship_id' => $request->string('ship_id')->toString(),
        ];

        $voyages = $this->voyageService
            ->paginate($filters)
            ->through(fn (Voyage $voyage) => $this->transform($voyage));

        return Inertia::render('Voyages/Index', [
            'voyages' => $voyages,
            'filters' => $filters,
            'ships' => $this->shipService->options(activeOnly: false),
            'statuses' => $this->statusOptions(),
            'canManage' => $request->user()?->can('voyages.manage') ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Voyage::class);

        return Inertia::render('Voyages/Create', [
            'ships' => $this->shipService->options(),
            'defaults' => [
                'pol' => DefaultPorts::POL,
                'pod' => DefaultPorts::POD,
            ],
        ]);
    }

    public function store(StoreVoyageRequest $request): RedirectResponse
    {
        Gate::authorize('create', Voyage::class);

        $voyage = $this->voyageService->create($request->validated());

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Voyage created as draft.');
    }

    public function show(Voyage $voyage): Response
    {
        Gate::authorize('view', $voyage);

        $voyage->load([
            'ship:id,name,flag',
            'companies',
            'companies.company:id,name,contact_name,contact_phone',
            'cars.company:id,company_name,company_id',
            'expenses',
            'expenses.journalEntry:id,voucher_number,status',
            'revenueJournalEntry:id,voucher_number,status',
            'commissionJournalEntry:id,voucher_number,status',
        ]);

        $settlements = $this->voyageSettlementService->forVoyage($voyage);
        $settlementPosting = $this->voyageSettlementPostingService->postingStatus(
            $voyage,
            $settlements['summary']
        );

        return Inertia::render('Voyages/Show', [
            'voyage' => $this->transform($voyage, detailed: true),
            'companies' => $voyage->companies
                ->map(fn ($company) => $this->voyageCompanyService->transform($company))
                ->values()
                ->all(),
            'companyOptions' => $this->voyageCompanyService->companyOptions(),
            'cars' => $voyage->cars
                ->map(fn ($car) => $this->voyageCarService->transform($car))
                ->values()
                ->all(),
            'expenses' => $voyage->expenses
                ->sortByDesc('expense_date')
                ->values()
                ->map(fn ($expense) => $this->voyageExpenseService->transform($expense))
                ->all(),
            'expenseTotals' => $this->voyageExpenseService->totalsByCurrency($voyage),
            'expenseTypes' => VoyageExpenseType::options(),
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->value,
            ])->all(),
            'paymentAccounts' => [
                Currency::USD->value => $this->voyageExpensePostingService->paymentAccountOptions(Currency::USD->value),
                Currency::AED->value => $this->voyageExpensePostingService->paymentAccountOptions(Currency::AED->value),
            ],
            'settlements' => $settlements,
            'settlementPosting' => $settlementPosting,
            'companyMovements' => $this->companyLedgerService->voyageMovements($voyage),
            'importPreview' => session("voyage_import_preview.{$voyage->id}"),
            'canManage' => request()->user()?->can('voyages.manage') ?? false,
            'canPostAccounting' => (request()->user()?->can('voyages.manage') ?? false)
                && (request()->user()?->can('accounting.manage') ?? false),
            'statusSteps' => collect(VoyageStatus::cases())
                ->map(fn (VoyageStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'tone' => $status->tone(),
                ])
                ->all(),
            'transitions' => collect($voyage->status->allowedTransitions())
                ->map(fn (VoyageStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values()
                ->all(),
            'tracking' => $this->voyageTrackingService->tracking($voyage),
        ]);
    }

    public function tracking(Voyage $voyage): Response
    {
        Gate::authorize('view', $voyage);

        return Inertia::render('Voyages/Tracking', [
            'voyage' => $this->transform($voyage, detailed: true),
            'tracking' => $this->voyageTrackingService->tracking($voyage),
            'canManage' => request()->user()?->can('voyages.manage') ?? false,
        ]);
    }

    public function storeWaypoint(StoreWaypointRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('update', $voyage);

        $this->voyageTrackingService->addWaypoint($voyage, $request->validated());

        return redirect()
            ->route('voyages.tracking', $voyage)
            ->with('success', 'Waypoint added.');
    }

    public function destroyWaypoint(Voyage $voyage, \App\Models\VoyageWaypoint $waypoint): RedirectResponse
    {
        Gate::authorize('update', $voyage);
        abort_unless($waypoint->voyage_id === $voyage->id, 404);

        $this->voyageTrackingService->deleteWaypoint($waypoint);

        return redirect()
            ->route('voyages.tracking', $voyage)
            ->with('success', 'Waypoint deleted.');
    }

    public function edit(Voyage $voyage): Response
    {
        Gate::authorize('update', $voyage);

        return Inertia::render('Voyages/Edit', [
            'voyage' => $this->transform($voyage, detailed: true),
            'ships' => $this->shipService->options(activeOnly: false),
        ]);
    }

    public function update(UpdateVoyageRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('update', $voyage);

        $this->voyageService->update($voyage, $request->validated());

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Voyage updated successfully.');
    }

    public function transition(TransitionVoyageRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('transition', $voyage);

        $status = VoyageStatus::from($request->validated('status'));
        $this->voyageService->transition($voyage, $status);

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', "Voyage marked as {$status->label()}.");
    }

    public function destroy(Voyage $voyage): RedirectResponse
    {
        Gate::authorize('delete', $voyage);

        $this->voyageService->delete($voyage);

        return redirect()
            ->route('voyages.index')
            ->with('success', 'Voyage deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Voyage $voyage, bool $detailed = false): array
    {
        $payload = [
            'id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
            'sailing_date' => $voyage->sailing_date?->format('Y-m-d'),
            'arrival_date' => $voyage->arrival_date?->format('Y-m-d'),
            'pol' => $voyage->pol,
            'pod' => $voyage->pod,
            'captain' => $voyage->captain,
            'status' => $voyage->status->value,
            'status_label' => $voyage->status->label(),
            'status_tone' => $voyage->status->tone(),
            'ship' => $voyage->ship ? [
                'id' => $voyage->ship->id,
                'name' => $voyage->ship->name,
                'flag' => $voyage->ship->flag,
            ] : null,
            'ship_id' => $voyage->ship_id,
            'is_editable' => $voyage->isEditable(),
        ];

        if ($detailed) {
            $payload = [
                ...$payload,
                'cost_per_car_aed' => (string) $voyage->cost_per_car_aed,
                'captain_commission_aed' => (string) $voyage->captain_commission_aed,
                'purchase_price_aed' => (string) $voyage->purchase_price_aed,
                'notes' => $voyage->notes,
            ];
        }

        return $payload;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(VoyageStatus::cases())
            ->map(fn (VoyageStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }
}
