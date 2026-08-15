<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\LandTrips\UndoCompanyLandCarImportRequest;
use App\Models\Company;
use App\Models\LandTripCarImport;
use App\Services\LandTripCarImportLogService;
use App\Services\LandTripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LandTripImportLogController extends Controller
{
    public function __construct(
        private readonly LandTripCarImportLogService $importLogService,
        private readonly LandTripService $landTripService
    ) {}

    public function index(Request $request, Company $company): Response
    {
        Gate::authorize('viewAny', LandTripCarImport::class);

        return Inertia::render('LandTrips/ImportLogs', [
            'company' => $this->landTripService->transformCompanyHub($company->loadCount('landTrips')),
            'imports' => $this->importLogService->paginateForCompany($company),
            'canManage' => $request->user()?->can(Permission::LandTripsManage->value) ?? false,
            'importLog' => $this->importLogService->meta($company),
        ]);
    }

    public function undo(UndoCompanyLandCarImportRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('undo', LandTripCarImport::class);

        $this->importLogService->undoLatest($company, $request->user());

        return back()->with('success', 'Last Excel import undone.');
    }
}
