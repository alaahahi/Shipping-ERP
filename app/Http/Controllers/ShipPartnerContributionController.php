<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipPartnerContributions\BulkStoreShipPartnerContributionRequest;
use App\Http\Requests\ShipPartnerContributions\ImportShipPartnerContributionRequest;
use App\Http\Requests\ShipPartnerContributions\PostShipPartnerContributionRequest;
use App\Http\Requests\ShipPartnerContributions\StoreShipPartnerContributionRequest;
use App\Http\Requests\ShipPartnerContributions\UpdateShipPartnerContributionRequest;
use App\Models\Ship;
use App\Models\ShipPartnerContribution;
use App\Services\ShipExpenseLedgerImportService;
use App\Services\ShipPartnerContributionPostingService;
use App\Services\ShipPartnerContributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ShipPartnerContributionController extends Controller
{
    public function __construct(
        private readonly ShipPartnerContributionService $contributionService,
        private readonly ShipPartnerContributionPostingService $postingService,
        private readonly ShipExpenseLedgerImportService $ledgerImportService
    ) {}

    public function store(StoreShipPartnerContributionRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipPartnerContribution::class, $ship]);
        $this->contributionService->create($ship, [...$request->validated(), 'created_by' => $request->user()?->id]);

        return $this->back($ship, 'Partner payment added.');
    }

    public function bulkStore(BulkStoreShipPartnerContributionRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipPartnerContribution::class, $ship]);
        $count = $this->contributionService->createMany($ship, $request->validated('rows'), $request->user()?->id);

        return $this->back($ship, "{$count} partner payments added.");
    }

    public function import(ImportShipPartnerContributionRequest $request, Ship $ship): JsonResponse
    {
        Gate::authorize('create', [ShipPartnerContribution::class, $ship]);
        $validated = $request->validated();

        return response()->json($this->ledgerImportService->previewContributions(
            $ship,
            $request->file('file'),
            (int) $validated['owner_id'],
            $validated['currency']
        ));
    }

    public function update(
        UpdateShipPartnerContributionRequest $request,
        Ship $ship,
        ShipPartnerContribution $contribution
    ): RedirectResponse {
        $this->assertBelongs($ship, $contribution);
        Gate::authorize('update', $contribution);
        $this->contributionService->update($contribution, $request->validated());

        return $this->back($ship, 'Partner payment updated.');
    }

    public function destroy(Ship $ship, ShipPartnerContribution $contribution): RedirectResponse
    {
        $this->assertBelongs($ship, $contribution);
        Gate::authorize('delete', $contribution);
        $this->contributionService->delete($contribution);

        return $this->back($ship, 'Partner payment removed.');
    }

    public function post(
        PostShipPartnerContributionRequest $request,
        Ship $ship,
        ShipPartnerContribution $contribution
    ): RedirectResponse {
        $this->assertBelongs($ship, $contribution);
        Gate::authorize('post', $contribution);
        $this->postingService->post($contribution, (int) $request->validated('payment_account_id'), $request->user());

        return $this->back($ship, 'Partner payment posted to accounting.');
    }

    private function back(Ship $ship, string $message): RedirectResponse
    {
        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'partners'])
            ->with('success', $message);
    }

    private function assertBelongs(Ship $ship, ShipPartnerContribution $contribution): void
    {
        abort_unless($contribution->ship_id === $ship->id, 404);
    }
}
