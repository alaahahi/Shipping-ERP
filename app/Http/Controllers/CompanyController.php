<?php

namespace App\Http\Controllers;

use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Models\Company;
use App\Services\CompanyDirectChargeService;
use App\Services\CompanyLedgerService;
use App\Services\CompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly CompanyLedgerService $companyLedgerService,
        private readonly CompanyDirectChargeService $companyDirectChargeService
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Company::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'active' => $request->string('active')->toString(),
        ];

        $companies = $this->companyService
            ->paginate($filters)
            ->through(fn (Company $company) => $this->companyService->transform($company));

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => $filters,
            'canManage' => $request->user()?->can('voyages.manage') ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('Companies/Create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $company = $this->companyService->create($request->validated());

        return redirect()
            ->route('companies.show', $company)
            ->with('success', "Company «{$company->name}» created.");
    }

    public function show(Company $company): Response
    {
        Gate::authorize('view', $company);

        $ledger = $this->companyLedgerService->statement($company);

        return Inertia::render('Companies/Show', [
            'company' => $this->companyService->transform($company),
            'ledger' => $ledger,
            'creditAccounts' => $this->companyDirectChargeService->creditAccountOptions(),
            'defaultCreditAccountId' => $this->companyDirectChargeService->defaultCreditAccountId(),
            'canManage' => request()->user()?->can('voyages.manage') ?? false,
            'canCollect' => request()->user()?->can('accounting.manage') ?? false,
        ]);
    }

    public function edit(Company $company): Response
    {
        Gate::authorize('update', $company);

        return Inertia::render('Companies/Edit', [
            'company' => $this->companyService->transform($company),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);

        $this->companyService->update($company, $request->validated());

        return redirect()
            ->route('companies.show', $company)
            ->with('success', 'Company updated.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        Gate::authorize('delete', $company);

        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company deleted.');
    }
}
