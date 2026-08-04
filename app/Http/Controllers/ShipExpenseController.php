<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipExpenses\PostShipExpenseRequest;
use App\Http\Requests\ShipExpenses\StoreShipExpenseRequest;
use App\Http\Requests\ShipExpenses\UpdateShipExpenseRequest;
use App\Models\Ship;
use App\Models\ShipExpense;
use App\Services\ShipExpensePostingService;
use App\Services\ShipExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ShipExpenseController extends Controller
{
    public function __construct(
        private readonly ShipExpenseService $shipExpenseService,
        private readonly ShipExpensePostingService $shipExpensePostingService
    ) {}

    public function store(StoreShipExpenseRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipExpense::class, $ship]);

        $this->shipExpenseService->create($ship, [
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'expenses'])
            ->with('success', 'Ship expense added.');
    }

    public function update(
        UpdateShipExpenseRequest $request,
        Ship $ship,
        ShipExpense $expense
    ): RedirectResponse {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('update', $expense);

        $this->shipExpenseService->update($expense, $request->validated());

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'expenses'])
            ->with('success', 'Ship expense updated.');
    }

    public function destroy(Ship $ship, ShipExpense $expense): RedirectResponse
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('delete', $expense);

        $this->shipExpenseService->delete($expense);

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'expenses'])
            ->with('success', 'Ship expense removed.');
    }

    public function post(
        PostShipExpenseRequest $request,
        Ship $ship,
        ShipExpense $expense
    ): RedirectResponse {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('post', $expense);

        $this->shipExpensePostingService->post(
            $expense,
            (int) $request->validated('payment_account_id'),
            $request->user()
        );

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'expenses'])
            ->with('success', 'Ship expense posted to accounting.');
    }

    private function assertBelongsToShip(Ship $ship, ShipExpense $expense): void
    {
        abort_unless($expense->ship_id === $ship->id, 404);
    }
}
