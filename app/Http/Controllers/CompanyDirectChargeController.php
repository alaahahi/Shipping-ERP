<?php

namespace App\Http\Controllers;

use App\Http\Requests\Companies\StoreCompanyDirectChargeRequest;
use App\Models\Company;
use App\Models\CompanyDirectCharge;
use App\Services\CompanyDirectChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CompanyDirectChargeController extends Controller
{
    public function __construct(
        private readonly CompanyDirectChargeService $companyDirectChargeService
    ) {}

    public function store(StoreCompanyDirectChargeRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', CompanyDirectCharge::class);

        $charge = $this->companyDirectChargeService->createAndPost(
            $company,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('companies.show', $company)
            ->with('success', "Direct receivable {$charge->voucher_number} posted.");
    }
}
