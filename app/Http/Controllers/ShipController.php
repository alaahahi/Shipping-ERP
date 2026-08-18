<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\ShipExpenseType;
use App\Http\Requests\Ships\StoreShipRequest;
use App\Http\Requests\Ships\UpdateShipRequest;
use App\Models\Ship;
use App\Services\ShipExpensePostingService;
use App\Services\ShipExpenseService;
use App\Services\ShipOwnershipService;
use App\Services\ShipPartnerContributionService;
use App\Services\ShipPartnerSettlementService;
use App\Services\ShipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShipController extends Controller
{
    public function __construct(
        private readonly ShipService $shipService,
        private readonly ShipOwnershipService $shipOwnershipService,
        private readonly ShipExpenseService $shipExpenseService,
        private readonly ShipExpensePostingService $shipExpensePostingService,
        private readonly ShipPartnerContributionService $contributionService,
        private readonly ShipPartnerSettlementService $settlementService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ship::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'active' => $request->string('active')->toString(),
        ];

        $ships = $this->shipService
            ->paginate($filters)
            ->through(fn (Ship $ship) => [
                'id' => $ship->id,
                'name' => $ship->name,
                'flag' => $ship->flag,
                'imo_number' => $ship->imo_number,
                'call_sign' => $ship->call_sign,
                'default_captain' => $ship->default_captain,
                'is_active' => $ship->is_active,
                'voyages_count' => $ship->voyages_count,
                'owners_count' => $ship->ownerships_count ?? 0,
            ]);

        return Inertia::render('Ships/Index', [
            'ships' => $ships,
            'filters' => $filters,
            'canManage' => $request->user()?->can('ships.manage') ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Ship::class);

        return Inertia::render('Ships/Create');
    }

    public function store(StoreShipRequest $request): RedirectResponse
    {
        Gate::authorize('create', Ship::class);

        $ship = $this->shipService->create($request->validated());

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'owners'])
            ->with('success', 'Ship created successfully. Add owners next.');
    }

    public function show(Ship $ship): Response
    {
        Gate::authorize('view', $ship);

        $ship->loadCount(['voyages', 'ownerships', 'expenses', 'partnerContributions']);
        $ship->load([
            'ownerships.owner',
            'expenses.journalEntry:id,voucher_number,status',
            'expenses.paidByOwner:id,name',
            'expenses.latestAttachment',
            'partnerContributions.owner:id,name',
            'partnerContributions.journalEntry:id,voucher_number,status',
        ]);

        $user = request()->user();

        return Inertia::render('Ships/Show', [
            'ship' => [
                'id' => $ship->id,
                'name' => $ship->name,
                'flag' => $ship->flag,
                'imo_number' => $ship->imo_number,
                'call_sign' => $ship->call_sign,
                'default_captain' => $ship->default_captain,
                'is_active' => $ship->is_active,
                'notes' => $ship->notes,
                'voyages_count' => $ship->voyages_count,
                'owners_count' => $ship->ownerships_count,
                'expenses_count' => $ship->expenses_count,
            ],
            'ownerships' => $ship->ownerships
                ->sortByDesc('share_percent')
                ->values()
                ->map(fn ($row) => $this->shipOwnershipService->transform($row))
                ->all(),
            'ownershipSummary' => $this->shipOwnershipService->summary($ship),
            'ownerOptions' => $this->shipOwnershipService->ownerOptions(),
            'expenses' => $ship->expenses
                ->sortByDesc('expense_date')
                ->values()
                ->map(fn ($expense) => $this->shipExpenseService->transform($expense))
                ->all(),
            'expenseTotals' => $this->shipExpenseService->totalsByCurrency($ship),
            'contributions' => $ship->partnerContributions
                ->sortByDesc('contribution_date')
                ->values()
                ->map(fn ($row) => $this->contributionService->transform($row))
                ->all(),
            'partnerSummaries' => [
                Currency::USD->value => $this->settlementService->summary($ship, Currency::USD->value),
                Currency::AED->value => $this->settlementService->summary($ship, Currency::AED->value),
            ],
            'expenseTypes' => ShipExpenseType::options(),
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'value' => $currency->value,
                'label' => $currency->value,
            ])->all(),
            'paymentAccounts' => [
                Currency::USD->value => $this->shipExpensePostingService->paymentAccountOptions(Currency::USD->value),
                Currency::AED->value => $this->shipExpensePostingService->paymentAccountOptions(Currency::AED->value),
            ],
            'canManage' => $user?->can('ships.manage') ?? false,
            'canPostAccounting' => ($user?->can('ships.manage') ?? false)
                && ($user?->can('accounting.manage') ?? false),
        ]);
    }

    public function edit(Ship $ship): Response
    {
        Gate::authorize('update', $ship);

        return Inertia::render('Ships/Edit', [
            'ship' => [
                'id' => $ship->id,
                'name' => $ship->name,
                'flag' => $ship->flag,
                'imo_number' => $ship->imo_number,
                'call_sign' => $ship->call_sign,
                'default_captain' => $ship->default_captain,
                'is_active' => $ship->is_active,
                'notes' => $ship->notes,
            ],
        ]);
    }

    public function update(UpdateShipRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('update', $ship);

        $this->shipService->update($ship, $request->validated());

        return redirect()
            ->route('ships.show', $ship)
            ->with('success', 'Ship updated successfully.');
    }

    public function destroy(Ship $ship): RedirectResponse
    {
        Gate::authorize('delete', $ship);

        $this->shipService->delete($ship);

        return redirect()
            ->route('ships.index')
            ->with('success', 'Ship deleted successfully.');
    }
}
