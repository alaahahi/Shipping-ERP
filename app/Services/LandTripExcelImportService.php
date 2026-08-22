<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarStatus;
use App\Models\User;
use App\Support\ApplicationTimezone;
use App\Support\ChassisLetterO;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
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
        private readonly LandTripCarStatusService $statusService,
        private readonly LandTripCarImportLogService $importLogService
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

    public function forgetUpload(): void
    {
        $path = session(self::SESSION_PATH);
        if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        session()->forget([self::SESSION_PATH, self::SESSION_NAME, self::SESSION_TRIP_ID]);
    }

    /**
     * @param  array{search?: string|null, location_status_id?: string|null, sort?: string|null, car_ids?: list<int>|null}  $filters
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
            'Color',
            'Year',
            'CMR',
            'VIN',
            '#',
            'Status',
            'Consignee',
            'Price',
            'Weight',
            'Notes',
            'Entered At',
        ], null, 'A2');
        $sheet->getStyle('A2:L2')->getFont()->setBold(true);

        $line = 3;
        $serial = 1;
        foreach ($cars as $car) {
            $sheet->setCellValue("A{$line}", (string) ($car->model ?: $car->description ?? ''));
            $sheet->setCellValue("B{$line}", (string) ($car->color ?? ''));
            $sheet->setCellValue("C{$line}", $car->year !== null ? (string) $car->year : '');
            $sheet->setCellValueExplicit("D{$line}", (string) ($car->cmr_waybill ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$line}", (string) ($car->chassis_no ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("F{$line}", $serial);
            $sheet->setCellValue("G{$line}", $car->locationStatus?->localizedName() ?? '');
            $sheet->setCellValue("H{$line}", (string) ($car->consignee_name ?? ''));
            $sheet->setCellValue("I{$line}", (int) round((float) ($car->price ?? 0)));
            $sheet->setCellValue("J{$line}", $car->weight !== null ? (string) $car->weight : '');
            $sheet->setCellValue("K{$line}", (string) ($car->notes ?? ''));
            $sheet->setCellValue("L{$line}", optional($car->created_at)?->format('Y-m-d H:i') ?? '');
            $line++;
            $serial++;
        }

        foreach (range('A', 'L') as $column) {
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
     * English-only PDF so shared hosts without ar-php still download a readable file.
     *
     * @param  array{search?: string|null, location_status_id?: string|null, sort?: string|null, car_ids?: list<int>|null}  $filters
     */
    public function exportCompanyCarsPdf(Company $company, array $filters = []): Response
    {
        $cars = $this->landTripService->listCompanyCarsForExport($company, $filters);
        $slug = Str::slug($company->name) ?: 'company-'.$company->id;
        $filename = 'sorted-inventory-'.$slug.'-'.now()->format('Ymd-His').'.pdf';

        $rows = [];
        $serial = 1;
        $totalPrice = 0.0;

        foreach ($cars as $car) {
            $price = (float) ($car->price ?? 0);
            $totalPrice += $price;
            $rows[] = [
                'serial' => $serial,
                'model' => (string) ($car->model ?: $car->description ?? ''),
                'color' => (string) ($car->color ?? ''),
                'year' => $car->year !== null ? (string) $car->year : '',
                'cmr' => (string) ($car->cmr_waybill ?? ''),
                'vin' => (string) ($car->chassis_no ?? ''),
                'status' => (string) ($car->locationStatus?->localizedName('en') ?: ''),
                'consignee' => (string) ($car->consignee_name ?? ''),
                'price' => number_format($price, 2, '.', ''),
                'weight' => $car->weight !== null ? (string) $car->weight : '',
                'notes' => (string) ($car->notes ?? ''),
                'entered_at' => optional($car->created_at)?->format('Y-m-d H:i') ?? '',
            ];
            $serial++;
        }

        return Pdf::loadView('reports.land-trip-cars-pdf', [
            'company' => $company->name,
            'rows' => $rows,
            'selected' => ($filters['car_ids'] ?? []) !== [],
            'search' => trim((string) ($filters['search'] ?? '')),
            'count' => count($rows),
            'total_price' => number_format($totalPrice, 2, '.', ''),
            'generated_at' => ApplicationTimezone::formatNowLabel(),
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    /**
     * @return array{
     *     original_name: string|null,
     *     default_consignee: string|null,
     *     occupied_chassis: list<string>,
     *     company_chassis: list<string>,
     *     ready: int,
     *     skipped: int,
     *     unmatched_status: int,
     *     invalid: int,
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
            'occupied_chassis' => array_keys($this->occupiedElsewhere($trip)),
            'company_chassis' => array_keys($this->occupiedOnCompany($trip)),
            'ready' => $parsed['ready'],
            'skipped' => $parsed['skipped'],
            'unmatched_status' => $parsed['unmatched_status'],
            'invalid' => $parsed['invalid'],
            'rows' => $parsed['rows'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, updated: int, skipped: int, created_ids: list<int>}
     */
    public function confirm(LandTrip $trip, User $actor, array $rows): array
    {
        $trip->loadMissing('company');

        $evaluated = $this->evaluateRows($trip, $rows);
        $readyRows = array_values(array_filter(
            $evaluated['rows'],
            static fn (array $row): bool => ($row['status'] ?? '') === 'ready'
        ));

        if ($readyRows === []) {
            throw ValidationException::withMessages([
                'rows' => 'No valid cars to import. Fix chassis numbers in the preview first.',
            ]);
        }

        $originalName = session(self::SESSION_NAME);
        $result = DB::transaction(function () use ($trip, $actor, $readyRows, $originalName): array {
            $result = $this->landTripService->upsertCompanyImportedCars(
                $trip->company,
                $trip,
                $readyRows,
            );

            $this->importLogService->record(
                $trip->company,
                $trip,
                $actor,
                is_string($originalName) ? $originalName : null,
                $result,
            );

            return $result;
        });

        $this->forgetUpload();

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     ready: int,
     *     skipped: int,
     *     unmatched_status: int,
     *     invalid: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function evaluateRows(LandTrip $trip, array $rows): array
    {
        $occupied = $this->occupiedElsewhere($trip);
        $companyChassis = $this->occupiedOnCompany($trip);
        $seen = [];
        $preview = [];
        $ready = 0;
        $skipped = 0;
        $unmatchedStatus = 0;
        $invalid = 0;
        $consigneeFallback = $trip->company?->name ?? 'Consignee';

        foreach (array_values($rows) as $index => $row) {
            $evaluated = $this->evaluateRow(
                is_array($row) ? $row : [],
                $index,
                $occupied,
                $companyChassis,
                $seen,
                $consigneeFallback,
            );

            if (($evaluated['reason_code'] ?? null) === 'missing_chassis') {
                continue;
            }

            if (($evaluated['status_text'] ?? '') !== '' && empty($evaluated['location_status_id'])) {
                $unmatchedStatus++;
            }

            if (($evaluated['reason_code'] ?? null) === 'invalid_chassis') {
                $invalid++;
            }

            if ($evaluated['status'] === 'ready') {
                $ready++;
            } else {
                $skipped++;
            }

            $normalized = $evaluated['normalized_chassis'] ?? null;
            if (is_string($normalized) && $normalized !== '' && ! in_array($evaluated['reason_code'], ['chassis_used', 'duplicate_in_file'], true)) {
                $seen[$normalized] = true;
            }

            unset($evaluated['normalized_chassis']);
            $preview[] = $evaluated;
        }

        return [
            'ready' => $ready,
            'skipped' => $skipped,
            'unmatched_status' => $unmatchedStatus,
            'invalid' => $invalid,
            'rows' => $preview,
        ];
    }

    /**
     * @return array{
     *     default_consignee: string|null,
     *     ready: int,
     *     skipped: int,
     *     unmatched_status: int,
     *     invalid: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    private function parseFile(string $absolutePath, LandTrip $trip): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheetByName('Sorted Inventory') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);

        $raw = [];
        foreach ($rows as $index => $row) {
            $raw[] = [
                'row_number' => $index + 1,
                'cells' => array_map(static fn ($value) => trim((string) ($value ?? '')), $row),
            ];
        }

        $defaultConsignee = null;
        $columns = null;
        $headerRowNumber = null;

        foreach ($raw as $item) {
            $cells = $item['cells'];
            if ($this->isEmptyRow($cells)) {
                continue;
            }

            if ($this->isHeaderRow($cells)) {
                $columns = $this->mapColumns($cells);
                $headerRowNumber = $item['row_number'];

                continue;
            }

            if ($defaultConsignee === null && $this->looksLikeConsigneeHeader($cells)) {
                $defaultConsignee = trim($cells[0] ?? '');
            }
        }

        $columns = $this->completeColumns($columns, $raw, $headerRowNumber);
        $consignee = $defaultConsignee ?: ($trip->company?->name ?? 'Consignee');
        $extracted = [];

        foreach ($raw as $item) {
            $rowNumber = $item['row_number'];
            $cells = $item['cells'];

            if ($this->isEmptyRow($cells) || $this->isHeaderRow($cells) || $rowNumber === $headerRowNumber) {
                continue;
            }

            if ($this->looksLikeConsigneeHeader($cells) || $this->looksLikeSummaryRow($cells)) {
                continue;
            }

            $model = $this->sanitizeDescription($this->cell($cells, $columns['model'] ?? null));
            $color = $this->sanitizeColor($this->cell($cells, $columns['color'] ?? null));
            $year = $this->parseYear($this->cell($cells, $columns['year'] ?? null));
            $cmr = $this->cell($cells, $columns['cmr'] ?? null);
            $serial = $this->cell($cells, $columns['serial'] ?? null);
            $statusText = $this->cell($cells, $columns['status'] ?? null);
            $notes = $this->sanitizeNotes($this->cell($cells, $columns['notes'] ?? null));
            $chassisRaw = $this->rawVinFromRow($cells, $columns['vin'] ?? null);

            $chassis = $this->sanitizeChassis($chassisRaw);
            if ($chassis === '') {
                continue;
            }

            $sortOrder = ctype_digit($serial) ? (int) $serial : $rowNumber;

            $extracted[] = [
                'row_number' => $sortOrder,
                'model' => $model,
                'color' => $color,
                'year' => $year,
                'description' => $model,
                'notes' => $notes,
                'cmr_waybill' => $this->sanitizeCmr($cmr),
                'chassis_no' => $chassis,
                'status_text' => $statusText,
                'consignee_name' => $consignee,
            ];
        }

        $evaluated = $this->evaluateRows($trip, $extracted);
        $evaluated['default_consignee'] = $defaultConsignee;

        return $evaluated;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $occupied
     * @param  array<string, true>  $companyChassis
     * @param  array<string, true>  $seen
     * @return array<string, mixed>
     */
    private function evaluateRow(array $row, int $index, array $occupied, array $companyChassis, array $seen, string $consigneeFallback): array
    {
        $rawChassis = trim((string) ($row['chassis_no'] ?? ''));
        [$normalized, $chassisError] = $this->inspectChassis($rawChassis);
        $model = $this->sanitizeDescription($row['model'] ?? $row['description'] ?? '');
        $color = $this->sanitizeColor($row['color'] ?? '');
        $year = $this->parseYear((string) ($row['year'] ?? ''));
        $notes = $this->sanitizeNotes($row['notes'] ?? '');
        $description = $this->sanitizeDescription($row['description'] ?? $model);
        $statusText = trim((string) ($row['status_text'] ?? ''));
        $status = $this->resolveStatus($row);
        $reasonCode = null;

        if ($chassisError === 'missing_chassis') {
            $reasonCode = 'missing_chassis';
        } elseif ($normalized !== null && isset($occupied[$normalized])) {
            $reasonCode = 'chassis_used';
        } elseif ($normalized !== null && isset($seen[$normalized])) {
            $reasonCode = 'duplicate_in_file';
        } elseif (mb_strlen($model) > 180 || mb_strlen($description) > 255) {
            $reasonCode = 'description_too_long';
        } elseif ($normalized !== null && isset($companyChassis[$normalized])) {
            $reasonCode = 'already_in_company';
        } elseif ($chassisError === 'invalid_chassis') {
            $reasonCode = 'invalid_chassis';
        }

        $blocking = in_array($reasonCode, ['missing_chassis', 'chassis_used', 'duplicate_in_file', 'description_too_long'], true);
        $ready = $normalized !== null && ! $blocking;

        return [
            'row_number' => (int) ($row['row_number'] ?? ($index + 1)),
            'status' => $ready ? 'ready' : 'skipped',
            'reason_code' => $reasonCode,
            'reason' => $reasonCode,
            'model' => $model,
            'color' => $color,
            'year' => $year,
            'notes' => $notes,
            'description' => $description !== '' ? $description : $model,
            'cmr_waybill' => $this->sanitizeCmr($row['cmr_waybill'] ?? ''),
            'chassis_no' => $normalized ?? '',
            'normalized_chassis' => $normalized,
            'status_text' => $statusText,
            'location_status_id' => $status?->id,
            'location_status_code' => $status?->code,
            'location_status_label' => $status?->localizedName(),
            'location_status_tone' => $status?->row_tone?->value,
            'consignee_name' => trim((string) ($row['consignee_name'] ?? '')) ?: $consigneeFallback,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveStatus(array $row): ?LandTripCarStatus
    {
        if (! empty($row['location_status_id'])) {
            $status = LandTripCarStatus::query()->find((int) $row['location_status_id']);
            if ($status instanceof LandTripCarStatus) {
                return $status;
            }
        }

        return $this->statusService->resolveByText($row['status_text'] ?? null);
    }

    /**
     * @return array<string, true>
     */
    private function occupiedElsewhere(LandTrip $trip): array
    {
        $companyId = (int) $trip->company_id;
        if ($companyId < 1) {
            return [];
        }

        $occupied = [];
        foreach (
            LandTripCar::query()
                ->whereNotNull('chassis_no')
                ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', '!=', $companyId))
                ->pluck('chassis_no') as $value
        ) {
            $normalized = $this->sanitizeChassis((string) $value);
            if ($normalized !== '') {
                $occupied[$normalized] = true;
            }
        }

        return $occupied;
    }

    /**
     * @return array<string, true>
     */
    private function occupiedOnCompany(LandTrip $trip): array
    {
        $companyId = (int) $trip->company_id;
        if ($companyId < 1) {
            return [];
        }

        $occupied = [];
        foreach (
            LandTripCar::query()
                ->whereNotNull('chassis_no')
                ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $companyId))
                ->pluck('chassis_no') as $value
        ) {
            $normalized = $this->sanitizeChassis((string) $value);
            if ($normalized !== '') {
                $occupied[$normalized] = true;
            }
        }

        return $occupied;
    }

    /**
     * @param  list<string>  $cells
     */
    private function looksLikeConsigneeHeader(array $cells): bool
    {
        $first = trim($cells[0] ?? '');
        if ($first === '' || is_numeric($first) || $this->isHeaderRow($cells)) {
            return false;
        }

        $rest = array_slice($cells, 1);
        foreach ($rest as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return $this->validChassis($first) === null && ! $this->looksLikeSummaryRow($cells);
    }

    /**
     * @param  list<string>  $cells
     */
    private function looksLikeSummaryRow(array $cells): bool
    {
        if ($this->firstChassisInRow($cells) !== null) {
            return false;
        }

        $joined = implode(' ', array_filter($cells));

        return (bool) preg_match('/\b(total|subtotal|count)\b/i', $joined)
            || (bool) preg_match('/^\s*\d+\s+(corolla|elantra|byd|cross)\b/i', $joined);
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            $value = strtolower($cell);
            if (
                str_contains($value, 'vehicle model')
                || str_contains($value, 'vin number')
                || preg_match('/\bvin\b/', $value)
                || (str_contains($value, 'cmr') && str_contains($value, 'waybill'))
            ) {
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

            if (str_contains($value, 'vehicle model') || $value === 'car name' || $value === 'model' || str_contains($value, 'موديل') || str_contains($value, 'مۆدێل')) {
                $map['model'] = $index;
            } elseif (str_contains($value, 'color') || str_contains($value, 'colour') || str_contains($value, 'لون') || str_contains($value, 'ڕەنگ') || str_contains($value, 'رەنگ')) {
                $map['color'] = $index;
            } elseif ($value === 'year' || str_contains($value, 'model year') || str_contains($value, 'سنة') || str_contains($value, 'ساڵ') || str_contains($value, 'سال')) {
                $map['year'] = $index;
            } elseif (str_contains($value, 'cmr') || str_contains($value, 'waybill')) {
                $map['cmr'] = $index;
            } elseif (preg_match('/\bvin\b/', $value) || str_contains($value, 'chassis')) {
                $map['vin'] = $index;
            } elseif ($value === '#' || $value === 'no' || $value === 'no.') {
                $map['serial'] = $index;
            } elseif (str_contains($value, 'status') || str_contains($value, 'location')) {
                $map['status'] = $index;
            } elseif (str_contains($value, 'note') || str_contains($value, 'ملاحظ') || str_contains($value, 'تێبینی')) {
                $map['notes'] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>|null  $columns
     * @param  list<array{row_number: int, cells: list<string>}>  $raw
     * @return array<string, int>
     */
    private function completeColumns(?array $columns, array $raw, ?int $headerRowNumber): array
    {
        $columns ??= [];
        $inferred = $this->inferColumns($raw, $headerRowNumber);

        foreach ($inferred as $key => $index) {
            if (! isset($columns[$key])) {
                $columns[$key] = $index;
            }
        }

        if (! isset($columns['status'])) {
            $columns['status'] = ($columns['vin'] ?? 3) + 1;
        }

        return $columns;
    }

    /**
     * @param  list<array{row_number: int, cells: list<string>}>  $raw
     * @return array<string, int>
     */
    private function inferColumns(array $raw, ?int $headerRowNumber): array
    {
        $serialLike = 0;
        $vinAt = [2 => 0, 3 => 0];
        $samples = 0;

        foreach ($raw as $item) {
            if ($item['row_number'] === $headerRowNumber) {
                continue;
            }

            $cells = $item['cells'];
            if ($this->isEmptyRow($cells) || $this->looksLikeConsigneeHeader($cells) || $this->looksLikeSummaryRow($cells)) {
                continue;
            }

            $samples++;
            if (preg_match('/^\d{1,4}$/', $cells[0] ?? '')) {
                $serialLike++;
            }
            if ($this->validChassis($cells[3] ?? '') !== null) {
                $vinAt[3]++;
            }
            if ($this->validChassis($cells[2] ?? '') !== null) {
                $vinAt[2]++;
            }
        }

        if ($samples > 0 && $serialLike >= ($samples / 2) && $vinAt[3] >= $vinAt[2]) {
            return [
                'serial' => 0,
                'model' => 1,
                'cmr' => 2,
                'vin' => 3,
                'status' => 4,
            ];
        }

        return [
            'model' => 0,
            'cmr' => 1,
            'vin' => 2,
            'serial' => 3,
            'status' => 4,
        ];
    }

    /**
     * @param  list<string>  $cells
     */
    private function rawVinFromRow(array $cells, ?int $vinIndex): string
    {
        $fromCol = $this->cell($cells, $vinIndex);
        if ($fromCol !== '') {
            return $fromCol;
        }

        foreach ($cells as $index => $cell) {
            if ($vinIndex !== null && $index === $vinIndex) {
                continue;
            }

            if ($this->validChassis($cell) !== null) {
                return trim($cell);
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $cells
     */
    private function firstChassisInRow(array $cells): ?string
    {
        foreach ($cells as $cell) {
            $chassis = $this->validChassis($cell);
            if ($chassis !== null) {
                return $chassis;
            }
        }

        return null;
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

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function inspectChassis(?string $value): array
    {
        $chassis = $this->sanitizeChassis($value);
        if ($chassis === '') {
            return [null, 'missing_chassis'];
        }

        if (strlen($chassis) !== 17) {
            return [$chassis, 'invalid_chassis'];
        }

        return [$chassis, null];
    }

    private function sanitizeChassis(?string $value): string
    {
        return ChassisLetterO::replace(strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value)));
    }

    private function sanitizeCmr(?string $value): string
    {
        $cleaned = (string) preg_replace('/[^A-Za-z0-9\s\-\/]/', '', (string) $value);
        $cleaned = (string) preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }

    private function sanitizeDescription(?string $value): string
    {
        $cleaned = (string) preg_replace('/[^\p{Arabic}A-Za-z\s]/u', '', (string) $value);
        $cleaned = (string) preg_replace('/\s+/u', ' ', $cleaned);

        return trim($cleaned);
    }

    private function sanitizeColor(?string $value): string
    {
        $cleaned = (string) preg_replace('/[^\p{Arabic}A-Za-z\s\-]/u', '', (string) $value);
        $cleaned = (string) preg_replace('/\s+/u', ' ', $cleaned);

        return trim($cleaned);
    }

    private function sanitizeNotes(?string $value): string
    {
        $cleaned = (string) preg_replace('/\s+/u', ' ', trim((string) $value));

        return mb_substr($cleaned, 0, 1000);
    }

    private function parseYear(?string $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (is_numeric($text)) {
            $year = (int) $text;

            return $year >= 1980 && $year <= 2100 ? $year : null;
        }

        if (preg_match('/(19|20)\d{2}/', $text, $matches) === 1) {
            $year = (int) $matches[0];

            return $year >= 1980 && $year <= 2100 ? $year : null;
        }

        return null;
    }

    private function validChassis(string $value): ?string
    {
        $chassis = $this->sanitizeChassis($value);

        return strlen($chassis) < 8 ? null : $chassis;
    }
}
