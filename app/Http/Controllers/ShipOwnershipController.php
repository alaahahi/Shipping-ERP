<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShipOwnerships\StoreShipOwnershipRequest;
use App\Http\Requests\ShipOwnerships\UpdateShipOwnershipRequest;
use App\Models\Ship;
use App\Models\ShipOwnership;
use App\Services\ShipOwnershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ShipOwnershipController extends Controller
{
    public function __construct(
        private readonly ShipOwnershipService $shipOwnershipService
    ) {}

    public function store(StoreShipOwnershipRequest $request, Ship $ship): RedirectResponse
    {
        Gate::authorize('create', [ShipOwnership::class, $ship]);

        $this->shipOwnershipService->attach($ship, $request->validated());

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'owners'])
            ->with('success', 'Ship owner linked.');
    }

    public function update(
        UpdateShipOwnershipRequest $request,
        Ship $ship,
        ShipOwnership $ownership
    ): RedirectResponse {
        $this->assertBelongsToShip($ship, $ownership);
        Gate::authorize('update', $ownership);

        $this->shipOwnershipService->update($ownership, $request->validated());

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'owners'])
            ->with('success', 'Ownership updated.');
    }

    public function destroy(Ship $ship, ShipOwnership $ownership): RedirectResponse
    {
        $this->assertBelongsToShip($ship, $ownership);
        Gate::authorize('delete', $ownership);

        $this->shipOwnershipService->detach($ownership);

        return redirect()
            ->route('ships.show', ['ship' => $ship, 'tab' => 'owners'])
            ->with('success', 'Owner removed from ship.');
    }

    private function assertBelongsToShip(Ship $ship, ShipOwnership $ownership): void
    {
        abort_unless($ownership->ship_id === $ship->id, 404);
    }
}
