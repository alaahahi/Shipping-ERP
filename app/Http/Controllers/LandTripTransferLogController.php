<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\LandTrip;
use App\Services\LandTripCarTransferService;
use App\Services\LandTripService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LandTripTransferLogController extends Controller
{
    public function __construct(
        private readonly LandTripCarTransferService $transferService,
        private readonly LandTripService $landTripService
    ) {}

    public function index(Request $request, Company $company): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        return Inertia::render('LandTrips/TransferLogs', [
            'company' => $this->landTripService->transformCompanyHub($company->loadCount('landTrips')),
            'transfers' => $this->transferService->paginateForCompany($company),
            'canManage' => $request->user()?->can(Permission::LandTripsManage->value) ?? false,
        ]);
    }
}
