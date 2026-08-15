<?php

namespace App\Http\Controllers;

use App\Http\Requests\LandTrips\DestroyCompanyWalletEntryRequest;
use App\Http\Requests\LandTrips\StoreCompanyWalletEntryRequest;
use App\Models\Company;
use App\Models\CompanyWalletEntry;
use App\Models\LandTrip;
use App\Services\CompanyWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyWalletController extends Controller
{
    public function __construct(
        private readonly CompanyWalletService $walletService
    ) {}

    public function store(StoreCompanyWalletEntryRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->walletService->create($company, $request->validated(), $request->user());

        return back()->with('success', 'Wallet entry saved.');
    }

    public function destroy(DestroyCompanyWalletEntryRequest $request, Company $company, CompanyWalletEntry $entry): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->walletService->delete($company, $entry, $request->user());

        return back()->with('success', 'Wallet entry deleted.');
    }

    public function print(Company $company, CompanyWalletEntry $entry): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        $payload = $this->walletService->printPayload($company, $entry);

        return Inertia::render('LandTrips/WalletPrint', $payload);
    }
}
