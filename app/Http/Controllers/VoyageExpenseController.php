<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoyageExpenses\PostVoyageExpenseRequest;
use App\Http\Requests\VoyageExpenses\StoreVoyageExpenseRequest;
use App\Http\Requests\VoyageExpenses\UpdateVoyageExpenseRequest;
use App\Models\Voyage;
use App\Models\VoyageExpense;
use App\Services\VoyageExpensePostingService;
use App\Services\VoyageExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class VoyageExpenseController extends Controller
{
    public function __construct(
        private readonly VoyageExpenseService $voyageExpenseService,
        private readonly VoyageExpensePostingService $voyageExpensePostingService
    ) {}

    public function store(StoreVoyageExpenseRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('create', [VoyageExpense::class, $voyage]);

        $this->voyageExpenseService->create($voyage, [
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Voyage expense added.');
    }

    public function update(
        UpdateVoyageExpenseRequest $request,
        Voyage $voyage,
        VoyageExpense $expense
    ): RedirectResponse {
        $this->assertBelongsToVoyage($voyage, $expense);
        Gate::authorize('update', $expense);

        $this->voyageExpenseService->update($expense, $request->validated());

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Voyage expense updated.');
    }

    public function destroy(Voyage $voyage, VoyageExpense $expense): RedirectResponse
    {
        $this->assertBelongsToVoyage($voyage, $expense);
        Gate::authorize('delete', $expense);

        $this->voyageExpenseService->delete($expense);

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Voyage expense removed.');
    }

    public function post(
        PostVoyageExpenseRequest $request,
        Voyage $voyage,
        VoyageExpense $expense
    ): RedirectResponse {
        $this->assertBelongsToVoyage($voyage, $expense);
        Gate::authorize('post', $expense);

        $this->voyageExpensePostingService->post(
            $expense,
            (int) $request->validated('payment_account_id'),
            $request->user()
        );

        return redirect()
            ->route('voyages.show', ['voyage' => $voyage, 'tab' => 'expenses'])
            ->with('success', 'Expense posted to accounting journal.');
    }

    private function assertBelongsToVoyage(Voyage $voyage, VoyageExpense $expense): void
    {
        abort_unless($expense->voyage_id === $voyage->id, 404);
    }
}
