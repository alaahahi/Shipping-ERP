<?php

namespace App\Http\Controllers;

use App\Http\Requests\LandTripCarStatuses\StoreLandTripCarStatusRequest;
use App\Http\Requests\LandTripCarStatuses\UpdateLandTripCarStatusRequest;
use App\Models\LandTripCarStatus;
use App\Services\LandTripCarStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LandTripCarStatusController extends Controller
{
    public function __construct(
        private readonly LandTripCarStatusService $statusService
    ) {}

    public function store(StoreLandTripCarStatusRequest $request): RedirectResponse
    {
        Gate::authorize('create', LandTripCarStatus::class);

        $status = $this->statusService->create($request->validated());

        return redirect()
            ->route('settings.edit', ['tab' => 'land_car_statuses'])
            ->with('success', "Location status «{$status->localizedName()}» added.");
    }

    public function update(UpdateLandTripCarStatusRequest $request, LandTripCarStatus $land_trip_car_status): RedirectResponse
    {
        Gate::authorize('update', $land_trip_car_status);

        $this->statusService->update($land_trip_car_status, $request->validated());

        return redirect()
            ->route('settings.edit', ['tab' => 'land_car_statuses'])
            ->with('success', 'Land trip car location status updated.');
    }

    public function destroy(LandTripCarStatus $land_trip_car_status): RedirectResponse
    {
        Gate::authorize('delete', $land_trip_car_status);

        $this->statusService->delete($land_trip_car_status);

        return redirect()
            ->route('settings.edit', ['tab' => 'land_car_statuses'])
            ->with('success', 'Land trip car location status deleted.');
    }
}
