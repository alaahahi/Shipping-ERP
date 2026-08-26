<?php

namespace App\Services;

use App\Support\ApplicationTimezone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private readonly VoyageReportService $voyageReportService,
        private readonly LandTripCarReportService $landTripCarReportService
    ) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     ship_id?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     */
    public function voyagesExcel(array $filters): StreamedResponse
    {
        $rows = $this->voyageReportService->list($filters);
        $filename = 'voyage-report-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Voyages');

        $headers = [
            'Voyage',
            'Ship',
            'Sailing',
            'Status',
            'Route',
            'Cars',
            'Revenue USD',
            'Expenses USD',
            'Profit USD',
            'Commission AED',
        ];

        foreach ($headers as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue("{$column}1", $header);
        }

        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        $line = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['voyage_number'],
                $row['ship_name'] ?? '',
                $row['sailing_date'] ?? '',
                $row['status_label'] ?? $row['status'],
                $row['route'] ?? '',
                $row['cars_count'],
                $row['revenue_usd'],
                $row['expenses_usd'],
                $row['profit_usd'],
                $row['commission_aed'],
            ], null, "A{$line}");
            $line++;
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     ship_id?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     */
    public function voyagesPdf(array $filters): Response
    {
        $rows = $this->voyageReportService->list($filters);

        $totals = [
            'cars' => collect($rows)->sum(fn ($row) => (int) $row['cars_count']),
            'revenue_usd' => number_format(collect($rows)->sum(fn ($row) => (float) $row['revenue_usd']), 2, '.', ''),
            'expenses_usd' => number_format(collect($rows)->sum(fn ($row) => (float) $row['expenses_usd']), 2, '.', ''),
            'profit_usd' => number_format(collect($rows)->sum(fn ($row) => (float) $row['profit_usd']), 2, '.', ''),
            'commission_aed' => number_format(collect($rows)->sum(fn ($row) => (float) $row['commission_aed']), 2, '.', ''),
        ];

        $pdf = Pdf::loadView('reports.voyages-pdf', [
            'rows' => $rows,
            'filters' => $filters,
            'totals' => $totals,
            'generated_at' => ApplicationTimezone::formatNowLabel(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('voyage-report-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>}  $filters
     */
    public function landTripCarsExcel(array $filters): StreamedResponse
    {
        $cars = $this->landTripCarReportService->list($filters);
        $filename = 'land-trip-cars-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Land transit');

        $sheet->fromArray([
            'Company',
            'Country',
            'Location',
            'Vehicle Model',
            'Color',
            'Year',
            'CMR',
            'VIN',
            '#',
            'Consignee',
            'Price',
            'Weight',
            'Notes',
            'Entered At',
        ], null, 'A1');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);

        $line = 2;
        $serial = 1;
        foreach ($cars as $car) {
            $status = $car->locationStatus;
            $sheet->setCellValue("A{$line}", (string) ($car->landTrip?->company?->name ?? ''));
            $sheet->setCellValue("B{$line}", (string) ($status?->country?->localizedName('en') ?? ''));
            $sheet->setCellValue("C{$line}", (string) ($status?->localizedName('en') ?? ''));
            $sheet->setCellValue("D{$line}", (string) ($car->model ?: $car->description ?? ''));
            $sheet->setCellValue("E{$line}", (string) ($car->color ?? ''));
            $sheet->setCellValue("F{$line}", $car->year !== null ? (string) $car->year : '');
            $sheet->setCellValueExplicit("G{$line}", (string) ($car->cmr_waybill ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("H{$line}", (string) ($car->chassis_no ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("I{$line}", $serial);
            $sheet->setCellValue("J{$line}", (string) ($car->consignee_name ?? ''));
            $sheet->setCellValue("K{$line}", (int) round((float) ($car->price ?? 0)));
            $sheet->setCellValue("L{$line}", $car->weight !== null ? (string) $car->weight : '');
            $sheet->setCellValue("M{$line}", (string) ($car->notes ?? ''));
            $sheet->setCellValue("N{$line}", optional($car->created_at)?->format('Y-m-d H:i') ?? '');
            $line++;
            $serial++;
        }

        foreach (range('A', 'N') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>}  $filters
     */
    public function landTripCarsPdf(array $filters): Response
    {
        $cars = $this->landTripCarReportService->list($filters);
        $filename = 'land-trip-cars-'.now()->format('Ymd-His').'.pdf';

        $rows = [];
        $serial = 1;
        $totalPrice = 0.0;

        foreach ($cars as $car) {
            $price = (float) ($car->price ?? 0);
            $totalPrice += $price;
            $status = $car->locationStatus;
            $rows[] = [
                'serial' => $serial,
                'company' => (string) ($car->landTrip?->company?->name ?? ''),
                'country' => (string) ($status?->country?->localizedName('en') ?? ''),
                'location' => (string) ($status?->localizedName('en') ?? ''),
                'model' => (string) ($car->model ?: $car->description ?? ''),
                'color' => (string) ($car->color ?? ''),
                'year' => $car->year !== null ? (string) $car->year : '',
                'cmr' => (string) ($car->cmr_waybill ?? ''),
                'vin' => (string) ($car->chassis_no ?? ''),
                'consignee' => (string) ($car->consignee_name ?? ''),
                'price' => number_format($price, 2, '.', ''),
                'weight' => $car->weight !== null ? (string) $car->weight : '',
                'notes' => (string) ($car->notes ?? ''),
                'entered_at' => optional($car->created_at)?->format('Y-m-d H:i') ?? '',
            ];
            $serial++;
        }

        return Pdf::loadView('reports.land-trip-cars-report-pdf', [
            'rows' => $rows,
            'count' => count($rows),
            'total_price' => number_format($totalPrice, 2, '.', ''),
            'generated_at' => ApplicationTimezone::formatNowLabel(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
