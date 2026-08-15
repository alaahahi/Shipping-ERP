<?php

namespace App\Http\Controllers;

use App\Http\Requests\LandTrips\ConfirmImportLandTripCarsRequest;
use App\Http\Requests\LandTrips\ImportLandTripCarsRequest;
use App\Http\Requests\LandTrips\ResetImportLandTripCarsRequest;
use App\Models\LandTrip;
use App\Services\LandTripExcelImportService;
use App\Services\LandTripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LandTripImportController extends Controller
{
    public function __construct(
        private readonly LandTripExcelImportService $importService,
        private readonly LandTripService $landTripService,
    ) {}

    public function create(LandTrip $land_trip): Response
    {
        Gate::authorize('update', $land_trip);

        $preview = null;
        if (session(LandTripExcelImportService::SESSION_PATH)) {
            try {
                $preview = $this->importService->preview($land_trip);
            } catch (\Throwable) {
                $preview = null;
            }
        }

        return Inertia::render('LandTrips/Import', [
            'trip' => [
                'id' => $land_trip->id,
                'cmr_number' => $land_trip->cmr_number,
                'company_id' => $land_trip->company_id,
            ],
            'preview' => $preview,
            'carStatuses' => $this->landTripService->carStatusOptions(),
        ]);
    }

    public function preview(ImportLandTripCarsRequest $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('update', $land_trip);

        $this->importService->storeUpload($request->file('file'), $land_trip, $request->user());

        return redirect()
            ->route('land-trips.import', $land_trip)
            ->with('success', 'Excel uploaded. Review the preview then confirm import.');
    }

    public function reset(ResetImportLandTripCarsRequest $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('update', $land_trip);

        $this->importService->forgetUpload();

        return redirect()->route('land-trips.import', $land_trip);
    }

    public function confirm(ConfirmImportLandTripCarsRequest $request, LandTrip $land_trip): RedirectResponse
    {
        Gate::authorize('update', $land_trip);

        $result = $this->importService->confirm($land_trip, $request->user(), $request->validated('rows'));

        return redirect()
            ->route('land-trips.companies.show', $land_trip->company_id)
            ->with(
                'success',
                "Land trip import: {$result['imported']} new, {$result['updated']} updated, {$result['skipped']} skipped."
            );
    }
}
