<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipExpenses\BulkStoreShipExpenseRequest;
use App\Http\Requests\ShipExpenses\ImportShipExpenseLedgerRequest;
use App\Http\Requests\ShipExpenses\PostShipExpenseRequest;
use App\Http\Requests\ShipExpenses\StoreShipExpenseRequest;
use App\Http\Requests\ShipExpenses\UpdateShipExpenseRequest;
use App\Models\Ship;
use App\Models\ShipExpense;
use App\Services\ShipExpenseLedgerImportService;
use App\Services\ShipExpensePostingService;
use App\Services\ShipExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ShipExpenseController extends Controller
{
    public function __construct(
        private readonly ShipExpenseService $shipExpenseService,
        private readonly ShipExpensePostingService $shipExpensePostingService,
        private readonly ShipExpenseLedgerImportService $ledgerImportService
    ) {}

    public function store(StoreShipExpenseRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipExpense::class, $ship]);
        $this->shipExpenseService->create($ship, [...$request->validated(), 'created_by' => $request->user()?->id]);

        return $this->backToExpenses($ship, 'Ship expense added.');
    }

    public function bulkStore(BulkStoreShipExpenseRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipExpense::class, $ship]);
        $count = $this->shipExpenseService->createMany($ship, $request->validated('rows'), $request->user()?->id);

        return $this->backToExpenses($ship, "{$count} ship expenses added.");
    }

    public function import(ImportShipExpenseLedgerRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipExpense::class, $ship]);
        $result = $this->ledgerImportService->importExpenses(
            $ship,
            $request->file('file'),
            $request->validated('currency'),
            $request->user()?->id,
            isset($request->validated()['paid_by_owner_id'])
                ? (int) $request->validated('paid_by_owner_id')
                : null
        );

        return $this->backToExpenses(
            $ship,
            "Expense import: {$result['imported']} imported, {$result['skipped']} skipped."
            .($result['errors'] !== [] ? ' Some rows failed.' : '')
        );
    }

    public function update(UpdateShipExpenseRequest $request, Ship $ship, ShipExpense $expense): RedirectResponse
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('update', $expense);
        $this->shipExpenseService->update($expense, $request->validated());

        return $this->backToExpenses($ship, 'Ship expense updated.');
    }

    public function destroy(Ship $ship, ShipExpense $expense): RedirectResponse
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('delete', $expense);
        $this->shipExpenseService->delete($expense);

        return $this->backToExpenses($ship, 'Ship expense removed.');
    }

    public function post(PostShipExpenseRequest $request, Ship $ship, ShipExpense $expense): RedirectResponse
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('post', $expense);
        $data = $request->validated();
        $this->shipExpensePostingService->post(
            $expense,
            $request->user(),
            $data['mode'] ?? 'partner',
            isset($data['payment_account_id']) ? (int) $data['payment_account_id'] : null
        );

        return $this->backToExpenses($ship, 'Ship expense posted to accounting.');
    }

    private function backToExpenses(Ship $ship, string $message): RedirectResponse
    {
        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'expenses'])
            ->with('success', $message);
    }

    private function assertBelongsToShip(Ship $ship, ShipExpense $expense): void
    {
        abort_unless($expense->ship_id === $ship->id, 404);
    }
}
