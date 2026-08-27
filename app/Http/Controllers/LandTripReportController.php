<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\LandTripCarReportRequest;
use App\Services\LandTripCarReportService;
use App\Services\ReportExportService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandTripReportController extends Controller
{
    public function __construct(
        private readonly LandTripCarReportService $landTripCarReportService,
        private readonly ReportExportService $reportExportService
    ) {}

    public function index(LandTripCarReportRequest $request): InertiaResponse
    {
        Gate::authorize('viewReports');

        $filters = $request->filters();
        $cars = $this->landTripCarReportService
            ->paginate($filters)
            ->through(fn ($car) => $this->landTripCarReportService->transformCar($car));

        return Inertia::render('Reports/LandTrips', [
            'cars' => $cars,
            'filters' => $filters,
            'options' => $this->landTripCarReportService->filterOptions(),
            'scoped' => $this->landTripCarReportService->hasScope($filters),
            'missingChassis' => $this->landTripCarReportService->missingChassis($filters),
        ]);
    }

    public function exportExcel(LandTripCarReportRequest $request): StreamedResponse
    {
        Gate::authorize('viewReports');

        return $this->reportExportService->landTripCarsExcel($request->filters());
    }

    public function exportPdf(LandTripCarReportRequest $request): Response
    {
        Gate::authorize('viewReports');

        return $this->reportExportService->landTripCarsPdf($request->filters());
    }
}
