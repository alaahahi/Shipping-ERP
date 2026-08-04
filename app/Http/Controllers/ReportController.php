<?php

namespace App\Http\Controllers;

use App\Enums\VoyageStatus;
use App\Services\ReportExportService;
use App\Services\ShipService;
use App\Services\VoyageReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly VoyageReportService $voyageReportService,
        private readonly ReportExportService $reportExportService,
        private readonly ShipService $shipService
    ) {}

    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('viewReports');

        $from = $request->date('date_from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('date_to')?->endOfDay() ?? now()->endOfMonth();

        return Inertia::render('Reports/Index', [
            'overview' => $this->voyageReportService->overview($from, $to),
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
        ]);
    }

    public function voyages(Request $request): InertiaResponse
    {
        Gate::authorize('viewReports');

        $filters = $this->voyageFilters($request);

        return Inertia::render('Reports/Voyages', [
            'rows' => $this->voyageReportService->paginate($filters),
            'filters' => $filters,
            'ships' => $this->shipService->options(activeOnly: false),
            'statuses' => collect(VoyageStatus::cases())->map(fn (VoyageStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
        ]);
    }

    public function exportVoyagesExcel(Request $request): StreamedResponse
    {
        Gate::authorize('viewReports');

        return $this->reportExportService->voyagesExcel($this->voyageFilters($request));
    }

    public function exportVoyagesPdf(Request $request): Response
    {
        Gate::authorize('viewReports');

        return $this->reportExportService->voyagesPdf($this->voyageFilters($request));
    }

    /**
     * @return array{
     *     search: string,
     *     status: string,
     *     ship_id: string,
     *     date_from: string,
     *     date_to: string
     * }
     */
    private function voyageFilters(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'ship_id' => $request->string('ship_id')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ];
    }
}
