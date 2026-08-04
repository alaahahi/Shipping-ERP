<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private readonly VoyageReportService $voyageReportService
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
            'generated_at' => now()->format('Y-m-d H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('voyage-report-'.now()->format('Ymd-His').'.pdf');
    }
}
