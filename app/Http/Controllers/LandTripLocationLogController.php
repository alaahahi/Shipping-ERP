<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\LandTrips\UndoCompanyLandCarLocationChangeRequest;
use App\Models\Company;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Services\LandTripCarLocationChangeService;
use App\Services\LandTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LandTripLocationLogController extends Controller
{
    public function __construct(
        private readonly LandTripCarLocationChangeService $locationChangeService,
        private readonly LandTripService $landTripService
    ) {}

    public function index(Request $request, Company $company): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        return Inertia::render('LandTrips/LocationLogs', [
            'company' => $this->landTripService->transformCompanyHub($company->loadCount('landTrips')),
            'changes' => $this->locationChangeService->paginateForCompany($company),
            'canManage' => $request->user()?->can(Permission::LandTripsManage->value) ?? false,
            'locationLog' => $this->locationChangeService->meta($company),
        ]);
    }

    public function undo(UndoCompanyLandCarLocationChangeRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', LandTrip::class);

        $this->locationChangeService->undoLatest($company, $request->user());

        return back()->with('success', 'Last location change undone.');
    }

    public function carHistory(Company $company, LandTripCar $car): JsonResponse
    {
        Gate::authorize('viewAny', LandTrip::class);

        return response()->json(
            $this->locationChangeService->timelineForCar($company, $car)
        );
    }
}
