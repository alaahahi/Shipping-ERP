<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\LandTrips\RestoreCompanyLandCarDeletionRequest;
use App\Models\Company;
use App\Models\LandTripCarDeletion;
use App\Services\LandTripCarDeletionLogService;
use App\Services\LandTripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LandTripDeletionLogController extends Controller
{
    public function __construct(
        private readonly LandTripCarDeletionLogService $deletionLogService,
        private readonly LandTripService $landTripService
    ) {}

    public function index(Request $request, Company $company): Response
    {
        Gate::authorize('viewAny', LandTripCarDeletion::class);

        $search = trim($request->string('search')->toString());

        return Inertia::render('LandTrips/DeletionLogs', [
            'company' => $this->landTripService->transformCompanyHub($company->loadCount('landTrips')),
            'deletions' => $this->deletionLogService->paginateForCompany($company, ['search' => $search]),
            'canManage' => $request->user()?->can(Permission::LandTripsManage->value) ?? false,
            'filters' => ['search' => $search],
            'deletionLog' => $this->deletionLogService->meta($company),
        ]);
    }

    public function restore(RestoreCompanyLandCarDeletionRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('restore', LandTripCarDeletion::class);

        $restored = $this->deletionLogService->restore(
            $company,
            $request->user(),
            $request->integer('deletion_id'),
            $request->validated('item_ids') ?? []
        );

        return back()->with('success', "Restored {$restored} cars.");
    }
}
