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
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>, chassis_nos?: list<string>, chassis_text?: string, duplicate_chassis?: list<string>}  $filters
     */
    public function landTripCarsExcel(array $filters): StreamedResponse
    {
        $cars = $this->landTripCarReportService->list($filters);
        $notes = $this->landTripCarReportService->chassisNotes($filters);
        $duplicateSet = array_flip($notes['duplicates']);
        $filename = 'land-trip-cars-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sorted Inventory');

        $sheet->setCellValue('A1', 'Sorted Inventory');
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $sheet->fromArray([
            '#',
            'Company',
            'Vehicle Model',
            'Color',
            'Year',
            'CMR',
            'VIN',
            'Duplicate',
            'Status',
            'Consignee',
            'Price',
            'Weight',
            'Notes',
            'Entered At',
        ], null, 'A2');
        $sheet->getStyle('A2:N2')->getFont()->setBold(true);

        $line = 3;
        $serial = 1;
        foreach ($cars as $car) {
            $status = $car->locationStatus;
            $normalized = $this->landTripCarReportService->normalizedChassis($car->chassis_no);
            $sheet->setCellValue("A{$line}", $serial);
            $sheet->setCellValue("B{$line}", (string) ($car->landTrip?->company?->name ?? ''));
            $sheet->setCellValue("C{$line}", (string) ($car->model ?: $car->description ?? ''));
            $sheet->setCellValue("D{$line}", (string) ($car->color ?? ''));
            $sheet->setCellValue("E{$line}", $car->year !== null ? (string) $car->year : '');
            $sheet->setCellValueExplicit("F{$line}", (string) ($car->cmr_waybill ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$line}", (string) ($car->chassis_no ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("H{$line}", $normalized !== null && isset($duplicateSet[$normalized]) ? 'Yes' : '');
            $sheet->setCellValue("I{$line}", (string) ($status?->localizedName('en') ?? ''));
            $sheet->setCellValue("J{$line}", (string) ($car->consignee_name ?? ''));
            $sheet->setCellValue("K{$line}", (int) round((float) ($car->price ?? 0)));
            $sheet->setCellValue("L{$line}", $car->weight !== null ? (string) $car->weight : '');
            $sheet->setCellValue("M{$line}", (string) ($car->notes ?? ''));
            $sheet->setCellValue("N{$line}", optional($car->created_at)?->format('Y-m-d H:i') ?? '');
            $line++;
            $serial++;
        }

        if ($notes['missing'] !== []) {
            $line += 1;
            $sheet->setCellValue("A{$line}", 'Not found chassis');
            $sheet->getStyle("A{$line}")->getFont()->setBold(true);
            $line++;
            $sheet->fromArray(['#', 'VIN', 'Duplicate'], null, "A{$line}");
            $sheet->getStyle("A{$line}:C{$line}")->getFont()->setBold(true);
            $line++;
            $missingSerial = 1;
            foreach ($notes['missing'] as $chassis) {
                $sheet->setCellValue("A{$line}", $missingSerial);
                $sheet->setCellValueExplicit("B{$line}", $chassis, DataType::TYPE_STRING);
                $sheet->setCellValue("C{$line}", isset($duplicateSet[$chassis]) ? 'Yes' : '');
                $line++;
                $missingSerial++;
            }
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
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>, chassis_nos?: list<string>, chassis_text?: string, duplicate_chassis?: list<string>}  $filters
     */
    public function landTripCarsPdf(array $filters): Response
    {
        $cars = $this->landTripCarReportService->list($filters);
        $notes = $this->landTripCarReportService->chassisNotes($filters);
        $duplicateSet = array_flip($notes['duplicates']);
        $filename = 'land-trip-cars-'.now()->format('Ymd-His').'.pdf';

        $rows = [];
        $serial = 1;
        $totalPrice = 0.0;

        foreach ($cars as $car) {
            $price = (float) ($car->price ?? 0);
            $totalPrice += $price;
            $status = $car->locationStatus;
            $normalized = $this->landTripCarReportService->normalizedChassis($car->chassis_no);
            $rows[] = [
                'serial' => $serial,
                'company' => (string) ($car->landTrip?->company?->name ?? ''),
                'model' => (string) ($car->model ?: $car->description ?? ''),
                'color' => (string) ($car->color ?? ''),
                'year' => $car->year !== null ? (string) $car->year : '',
                'cmr' => (string) ($car->cmr_waybill ?? ''),
                'vin' => (string) ($car->chassis_no ?? ''),
                'is_duplicate' => $normalized !== null && isset($duplicateSet[$normalized]),
                'status' => (string) ($status?->localizedName('en') ?? ''),
                'consignee' => (string) ($car->consignee_name ?? ''),
                'price' => number_format($price, 2, '.', ''),
                'weight' => $car->weight !== null ? (string) $car->weight : '',
                'notes' => (string) ($car->notes ?? ''),
                'entered_at' => optional($car->created_at)?->format('Y-m-d H:i') ?? '',
            ];
            $serial++;
        }

        $missing = [];
        $missingSerial = 1;
        foreach ($notes['missing'] as $chassis) {
            $missing[] = [
                'serial' => $missingSerial,
                'vin' => $chassis,
                'is_duplicate' => isset($duplicateSet[$chassis]),
            ];
            $missingSerial++;
        }

        return Pdf::loadView('reports.land-trip-cars-report-pdf', [
            'rows' => $rows,
            'missing' => $missing,
            'count' => count($rows),
            'total_price' => number_format($totalPrice, 2, '.', ''),
            'generated_at' => ApplicationTimezone::formatNowLabel(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
