<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoyageCompanies\StoreVoyageCompanyRequest;
use App\Http\Requests\VoyageCompanies\UpdateVoyageCompanyRequest;
use App\Models\Voyage;
use App\Models\VoyageCompany;
use App\Services\VoyageCompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class VoyageCompanyController extends Controller
{
    public function __construct(
        private readonly VoyageCompanyService $voyageCompanyService
    ) {}

    public function store(StoreVoyageCompanyRequest $request, Voyage $voyage): RedirectResponse
    {
        Gate::authorize('create', [VoyageCompany::class, $voyage]);

        $this->voyageCompanyService->create($voyage, $request->validated());

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Shipping company added to voyage.');
    }

    public function update(
        UpdateVoyageCompanyRequest $request,
        Voyage $voyage,
        VoyageCompany $company
    ): RedirectResponse {
        $this->assertBelongsToVoyage($voyage, $company);
        Gate::authorize('update', $company);

        $this->voyageCompanyService->update($company, $request->validated());

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Shipping company updated.');
    }

    public function destroy(Voyage $voyage, VoyageCompany $company): RedirectResponse
    {
        $this->assertBelongsToVoyage($voyage, $company);
        Gate::authorize('delete', $company);

        $this->voyageCompanyService->delete($company);

        return redirect()
            ->route('voyages.show', $voyage)
            ->with('success', 'Shipping company removed from voyage.');
    }

    private function assertBelongsToVoyage(Voyage $voyage, VoyageCompany $company): void
    {
        abort_unless($company->voyage_id === $voyage->id, 404);
    }
}
