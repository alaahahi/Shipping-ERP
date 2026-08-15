<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LandTrip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandTripExcelImportService
{
    public const SESSION_PATH = 'land_trips.import_path';

    public const SESSION_NAME = 'land_trips.import_name';

    public const SESSION_TRIP_ID = 'land_trips.import_trip_id';

    public const SHEET_NAME = 'Sorted Inventory';

    public function __construct(
        private readonly LandTripService $landTripService,
        private readonly LandTripCarStatusService $statusService
    ) {}

    /**
     * @return array{path: string, original_name: string}
     */
    public function storeUpload(UploadedFile $file, LandTrip $trip, User $actor): array
    {
        $path = $file->store("land-trip-imports/{$actor->id}", 'local');

        session([
            self::SESSION_PATH => $path,
            self::SESSION_NAME => $file->getClientOriginalName(),
            self::SESSION_TRIP_ID => $trip->id,
        ]);

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @param  array{search?: string|null, location_status_id?: string|null}  $filters
     */
    public function exportCompanyCars(Company $company, array $filters = []): StreamedResponse
    {
        $cars = $this->landTripService->listCompanyCarsForExport($company, $filters);
        $slug = Str::slug($company->name) ?: 'company-'.$company->id;
        $filename = 'sorted-inventory-'.$slug.'-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        $sheet->setCellValue('A1', $company->name);
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $sheet->fromArray([
            'Vehicle Model',
            'CMR',
            'VIN Number',
            '#',
            'Status',
        ], null, 'A2');
        $sheet->getStyle('A2:E2')->getFont()->setBold(true);

        $line = 3;
        $serial = 1;
        foreach ($cars as $car) {
            $sheet->setCellValue("A{$line}", (string) ($car->description ?? ''));
            $sheet->setCellValueExplicit("B{$line}", (string) ($car->cmr_waybill ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$line}", (string) ($car->chassis_no ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("D{$line}", $serial);
            $sheet->setCellValue("E{$line}", $car->locationStatus?->localizedName() ?? '');
            $line++;
            $serial++;
        }

        foreach (range('A', 'E') as $column) {
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
     * @return array{
     *     original_name: string|null,
     *     default_consignee: string|null,
     *     ready: int,
     *     skipped: int,
     *     unmatched_status: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function preview(LandTrip $trip, ?string $diskPath = null): array
    {
        $path = $diskPath ?? session(self::SESSION_PATH);
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'No Excel file found. Upload a file first.',
            ]);
        }

        if ((int) session(self::SESSION_TRIP_ID) !== (int) $trip->id) {
            throw ValidationException::withMessages([
                'file' => 'Uploaded file belongs to another land trip. Upload again.',
            ]);
        }

        $trip->loadMissing('company');

        $parsed = $this->parseFile(Storage::disk('local')->path($path), $trip);

        return [
            'original_name' => session(self::SESSION_NAME),
            'default_consignee' => $parsed['default_consignee'],
            'ready' => $parsed['ready'],
            'skipped' => $parsed['skipped'],
            'unmatched_status' => $parsed['unmatched_status'],
            'rows' => array_slice($parsed['rows'], 0, 120),
        ];
    }

    /**
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function confirm(LandTrip $trip, User $actor): array
    {
        $path = session(self::SESSION_PATH);
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'No Excel file found. Upload a file first.',
            ]);
        }

        if ((int) session(self::SESSION_TRIP_ID) !== (int) $trip->id) {
            throw ValidationException::withMessages([
                'file' => 'Uploaded file belongs to another land trip. Upload again.',
            ]);
        }

        $trip->loadMissing('company');

        $parsed = $this->parseFile(Storage::disk('local')->path($path), $trip);

        return DB::transaction(function () use ($trip, $parsed): array {
            $trip->load('cars');
            $existing = $trip->cars->keyBy(fn ($car) => strtoupper((string) $car->chassis_no));
            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $merged = [];

            foreach ($parsed['rows'] as $row) {
                if ($row['status'] !== 'ready') {
                    $skipped++;

                    continue;
                }

                $chassis = $row['chassis_no'];
                if ($existing->has($chassis)) {
                    $updated++;
                } else {
                    $imported++;
                }

                $merged[$chassis] = [
                    'voyage_car_id' => $existing->get($chassis)?->voyage_car_id,
                    'chassis_no' => $chassis,
                    'cmr_waybill' => $row['cmr_waybill'],
                    'consignee_name' => $row['consignee_name'],
                    'description' => $row['description'],
                    'weight' => $existing->get($chassis)?->weight,
                    'notes' => $existing->get($chassis)?->notes,
                    'location_status_id' => $row['location_status_id'],
                    'sort_order' => $row['row_number'],
                ];
            }

            foreach ($existing as $chassis => $car) {
                if (! isset($merged[$chassis])) {
                    $merged[$chassis] = [
                        'voyage_car_id' => $car->voyage_car_id,
                        'chassis_no' => $car->chassis_no,
                        'cmr_waybill' => $car->cmr_waybill,
                        'consignee_name' => $car->consignee_name,
                        'description' => $car->description,
                        'weight' => $car->weight,
                        'notes' => $car->notes,
                        'location_status_id' => $car->location_status_id,
                        'sort_order' => $car->sort_order,
                    ];
                }
            }

            $this->landTripService->syncCars($trip, array_values($merged));

            session()->forget([self::SESSION_PATH, self::SESSION_NAME, self::SESSION_TRIP_ID]);

            return [
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * @return array{
     *     default_consignee: string|null,
     *     ready: int,
     *     skipped: int,
     *     unmatched_status: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    private function parseFile(string $absolutePath, LandTrip $trip): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheetByName('Sorted Inventory') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        $defaultConsignee = null;
        $columns = null;
        $preview = [];
        $ready = 0;
        $skipped = 0;
        $unmatchedStatus = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $cells = array_map(static fn ($value) => trim((string) ($value ?? '')), $row);

            if ($this->isEmptyRow($cells)) {
                continue;
            }

            if ($defaultConsignee === null && $this->looksLikeConsigneeHeader($cells)) {
                $defaultConsignee = trim($cells[0]);

                continue;
            }

            if ($this->isHeaderRow($cells)) {
                $columns = $this->mapColumns($cells);

                continue;
            }

            if ($columns === null) {
                continue;
            }

            $model = $this->cell($cells, $columns['model'] ?? null);
            $cmr = $this->cell($cells, $columns['cmr'] ?? null);
            $chassis = $this->normalizeChassis($this->cell($cells, $columns['vin'] ?? null));
            $statusText = $this->cell($cells, $columns['status'] ?? null);

            if ($chassis === null && $model === '' && $cmr === '') {
                continue;
            }

            if ($chassis === null) {
                $skipped++;
                $preview[] = $this->previewRow($rowNumber, 'skipped', $model, $cmr, null, $statusText, null, null, null, 'Missing chassis / VIN');

                continue;
            }

            $status = $this->statusService->resolveByText($statusText);
            if ($statusText !== '' && ! $status) {
                $unmatchedStatus++;
            }

            $consignee = $defaultConsignee ?: ($trip->company?->name ?? 'Consignee');
            $ready++;
            $preview[] = $this->previewRow(
                $rowNumber,
                'ready',
                $model,
                $cmr,
                $chassis,
                $statusText,
                $status?->id,
                $status?->localizedName(),
                $status?->row_tone?->value,
                null,
                $consignee,
                $status?->code,
            );
        }

        return [
            'default_consignee' => $defaultConsignee,
            'ready' => $ready,
            'skipped' => $skipped,
            'unmatched_status' => $unmatchedStatus,
            'rows' => $preview,
        ];
    }

    /**
     * @param  list<string>  $cells
     */
    private function looksLikeConsigneeHeader(array $cells): bool
    {
        $first = trim($cells[0] ?? '');
        if ($first === '' || is_numeric($first)) {
            return false;
        }

        $joined = implode(' ', array_filter($cells));
        if ($this->isHeaderRow($cells)) {
            return false;
        }

        return ! preg_match('/(corolla|elantra|byd|vehicle|vin|cmr)/i', $joined);
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            $value = strtolower($cell);
            if (str_contains($value, 'vehicle model') || str_contains($value, 'vin number') || str_contains($value, 'cmr')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, int>
     */
    private function mapColumns(array $cells): array
    {
        $map = [];

        foreach ($cells as $index => $cell) {
            $value = strtolower(trim($cell));

            if (str_contains($value, 'vehicle model') || $value === 'car name') {
                $map['model'] = $index;
            } elseif (str_contains($value, 'cmr') || str_contains($value, 'waybill')) {
                $map['cmr'] = $index;
            } elseif (str_contains($value, 'vin')) {
                $map['vin'] = $index;
            } elseif ($value === '#' || $value === 'no') {
                $map['serial'] = $index;
            } elseif ($index >= 4 && ! isset($map['status'])) {
                $map['status'] = $index;
            }
        }

        if (! isset($map['status'])) {
            $map['status'] = 4;
        }

        return $map;
    }

    /**
     * @param  list<string>  $cells
     */
    private function cell(array $cells, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($cells[$index] ?? ''));
    }

    /**
     * @param  list<string>  $cells
     */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeChassis(string $value): ?string
    {
        $chassis = strtoupper((string) preg_replace('/\s+/', '', trim($value)));

        return strlen($chassis) < 8 ? null : $chassis;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewRow(
        int $rowNumber,
        string $status,
        string $model,
        string $cmr,
        ?string $chassis,
        string $statusText,
        ?int $locationStatusId,
        ?string $locationStatusLabel,
        ?string $locationStatusTone = null,
        ?string $reason = null,
        ?string $consigneeName = null,
        ?string $locationStatusCode = null
    ): array {
        return [
            'row_number' => $rowNumber,
            'status' => $status,
            'reason' => $reason,
            'description' => $model,
            'cmr_waybill' => $cmr,
            'chassis_no' => $chassis,
            'status_text' => $statusText,
            'location_status_id' => $locationStatusId,
            'location_status_code' => $locationStatusCode,
            'location_status_label' => $locationStatusLabel,
            'location_status_tone' => $locationStatusTone,
            'consignee_name' => $consigneeName,
        ];
    }
}
