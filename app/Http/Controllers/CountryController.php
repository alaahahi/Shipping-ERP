<?php

namespace App\Http\Controllers;

use App\Http\Requests\Countries\StoreCountryRequest;
use App\Http\Requests\Countries\UpdateCountryRequest;
use App\Models\Country;
use App\Services\CountryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CountryController extends Controller
{
    public function __construct(private readonly CountryService $countryService) {}

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Country::class);

        $country = $this->countryService->create($request->validated());

        return redirect()
            ->route('settings.edit', ['tab' => 'countries'])
            ->with('success', "Country «{$country->name}» added.");
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        Gate::authorize('update', $country);

        $this->countryService->update($country, $request->validated());

        return redirect()
            ->route('settings.edit', ['tab' => 'countries'])
            ->with('success', 'Country updated.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        Gate::authorize('delete', $country);

        $this->countryService->delete($country);

        return redirect()
            ->route('settings.edit', ['tab' => 'countries'])
            ->with('success', 'Country deleted.');
    }
}
