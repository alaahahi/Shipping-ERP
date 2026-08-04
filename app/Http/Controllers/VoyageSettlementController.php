<?php

namespace App\Http\Controllers;

use App\Http\Requests\Voyages\PostVoyageCommissionRequest;
use App\Models\Voyage;
use App\Services\VoyageSettlementPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VoyageSettlementController extends Controller
{
    public function __construct(
        private readonly VoyageSettlementPostingService $voyageSettlementPostingService
    ) {}

    public function postRevenue(Request $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('postRevenue', $voyage);

        $this->voyageSettlementPostingService->postRevenue($voyage, $request->user());

        return redirect()
            ->route('voyages.show', ['voyage' => $voyage, 'tab' => 'settlements'])
            ->with('success', 'Shipping revenue posted to accounting.');
    }

    public function postCommission(PostVoyageCommissionRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('postCommission', $voyage);

        $this->voyageSettlementPostingService->postCommission(
            $voyage,
            (int) $request->validated('payment_account_id'),
            $request->user()
        );

        return redirect()
            ->route('voyages.show', ['voyage' => $voyage, 'tab' => 'settlements'])
            ->with('success', 'Captain commission posted to accounting.');
    }
}
