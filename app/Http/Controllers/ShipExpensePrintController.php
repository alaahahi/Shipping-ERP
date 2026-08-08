<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Models\Ship;
use App\Services\ShipExpenseService;
use App\Services\ShipPartnerContributionService;
use App\Services\ShipPartnerSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShipExpensePrintController extends Controller
{
    public function __construct(
        private readonly ShipExpenseService $shipExpenseService,
        private readonly ShipPartnerContributionService $contributionService,
        private readonly ShipPartnerSettlementService $settlementService
    ) {}

    public function __invoke(Request $request, Ship $ship): Response
    {
        Gate::authorize('view', $ship);

        $currency = strtoupper((string) $request->query('currency', Currency::USD->value));
        if (! in_array($currency, Currency::values(), true)) {
            $currency = Currency::USD->value;
        }

        $ship->load(['ownerships.owner:id,name']);

        return Inertia::render('Ships/ExpensePrint', [
            'ship' => [
                'id' => $ship->id,
                'name' => $ship->name,
                'flag' => $ship->flag,
                'imo_number' => $ship->imo_number,
            ],
            'currency' => $currency,
            'currencies' => collect(Currency::cases())->map(fn (Currency $item) => [
                'value' => $item->value,
                'label' => $item->value,
            ])->all(),
            'printedAt' => now()->format('Y-m-d H:i'),
            'ownerships' => $ship->ownerships
                ->sortByDesc('share_percent')
                ->values()
                ->map(fn ($row) => [
                    'owner_id' => (int) $row->owner_id,
                    'owner_name' => $row->owner?->name,
                    'is_managing' => (bool) $row->is_managing,
                ])
                ->all(),
            'expenses' => $ship->expenses()
                ->with(['paidByOwner:id,name', 'journalEntry:id,voucher_number,status'])
                ->where('currency', $currency)
                ->orderBy('expense_date')
                ->orderBy('id')
                ->get()
                ->map(fn ($expense) => $this->shipExpenseService->transform($expense))
                ->all(),
            'contributions' => $ship->partnerContributions()
                ->with(['owner:id,name', 'journalEntry:id,voucher_number,status'])
                ->where('currency', $currency)
                ->orderBy('contribution_date')
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => $this->contributionService->transform($row))
                ->all(),
            'summary' => $this->settlementService->summary($ship, $currency),
        ]);
    }
}
