<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\LandTrip;
use App\Services\LandTripCarPriceChangeService;
use App\Services\LandTripService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LandTripPriceLogController extends Controller
{
    public function __construct(
        private readonly LandTripCarPriceChangeService $priceChangeService,
        private readonly LandTripService $landTripService
    ) {}

    public function index(Request $request, Company $company): Response
    {
        Gate::authorize('viewAny', LandTrip::class);

        return Inertia::render('LandTrips/PriceLogs', [
            'company' => $this->landTripService->transformCompanyHub($company->loadCount('landTrips')),
            'changes' => $this->priceChangeService->paginateForCompany($company),
            'canManage' => $request->user()?->can(Permission::LandTripsManage->value) ?? false,
        ]);
    }
}
