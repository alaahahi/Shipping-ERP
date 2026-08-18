<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipExpenses\BulkStoreShipExpenseRequest;
use App\Http\Requests\ShipExpenses\ImportShipExpenseLedgerRequest;
use App\Http\Requests\ShipExpenses\PostShipExpenseRequest;
use App\Http\Requests\ShipExpenses\StoreShipExpenseRequest;
use App\Http\Requests\ShipExpenses\UpdateShipExpenseRequest;
use App\Models\Ship;
use App\Models\ShipExpense;
use App\Services\AttachmentService;
use App\Services\ShipExpenseLedgerImportService;
use App\Services\ShipExpensePostingService;
use App\Services\ShipExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipExpenseController extends Controller
{
    public function __construct(
        private readonly ShipExpenseService $shipExpenseService,
        private readonly ShipExpensePostingService $shipExpensePostingService,
        private readonly ShipExpenseLedgerImportService $ledgerImportService,
        private readonly AttachmentService $attachmentService
    ) {}

    public function store(StoreShipExpenseRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipExpense::class, $ship]);
        $this->shipExpenseService->create(
            $ship,
            [...$request->safe()->except('attachment'), 'created_by' => $request->user()?->id],
            $request->file('attachment')
        );

        return $this->backToExpenses($ship, 'Ship expense added.');
    }

    public function bulkStore(BulkStoreShipExpenseRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipExpense::class, $ship]);
        $count = $this->shipExpenseService->createMany($ship, $request->validated('rows'), $request->user()?->id);

        return $this->backToExpenses($ship, "{$count} ship expenses added.");
    }

    public function import(ImportShipExpenseLedgerRequest $request, Ship $ship): JsonResponse
    {
        Gate::authorize('create', [ShipExpense::class, $ship]);
        $validated = $request->validated();

        return response()->json($this->ledgerImportService->previewExpenses(
            $ship,
            $request->file('file'),
            $validated['currency'],
            isset($validated['paid_by_owner_id']) ? (int) $validated['paid_by_owner_id'] : null
        ));
    }

    public function update(UpdateShipExpenseRequest $request, Ship $ship, ShipExpense $expense): RedirectResponse
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('update', $expense);
        $this->shipExpenseService->update(
            $expense,
            $request->safe()->except('attachment'),
            $request->file('attachment')
        );

        return $this->backToExpenses($ship, 'Ship expense updated.');
    }

    public function destroy(Request $request, Ship $ship, ShipExpense $expense): RedirectResponse
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('delete', $expense);
        $this->shipExpenseService->delete($expense, $request->user()?->id);

        return $this->backToExpenses($ship, 'Ship expense removed.');
    }

    public function voucher(Ship $ship, ShipExpense $expense): Response
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('view', $ship);

        return Inertia::render('Ships/ExpenseVoucherPrint', $this->shipExpenseService->printPayload($ship, $expense));
    }

    public function attachment(Ship $ship, ShipExpense $expense): StreamedResponse
    {
        $this->assertBelongsToShip($ship, $expense);
        Gate::authorize('view', $ship);

        return $this->attachmentService->inlineLatest($expense);
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
