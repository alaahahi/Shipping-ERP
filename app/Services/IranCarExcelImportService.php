<?php

namespace App\Services;

use App\Enums\IranBorder;
use App\Enums\IranCarSaleState;
use App\Models\IranCar;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IranCarExcelImportService
{
    public const SESSION_PATH = 'iran_cars.import_path';

    public const SESSION_NAME = 'iran_cars.import_name';

    public const SESSION_SALE_STATE = 'iran_cars.import_sale_state';

    public function __construct(
        private readonly IranCarService $iranCarService
    ) {}

    /**
     * @return array{path: string, original_name: string}
     */
    public function storeUpload(UploadedFile $file, User $actor, string $saleState = 'unsold'): array
    {
        $path = $file->store("iran-car-imports/{$actor->id}", 'local');

        session([
            self::SESSION_PATH => $path,
            self::SESSION_NAME => $file->getClientOriginalName(),
            self::SESSION_SALE_STATE => IranCarSaleState::tryFrom($saleState)?->value ?? IranCarSaleState::Unsold->value,
        ]);

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @return array{
     *     original_name: string|null,
     *     ready: int,
     *     duplicates: int,
     *     skipped: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function preview(?string $diskPath = null, ?int $defaultCompanyId = null, ?string $defaultBorder = null): array
    {
        $path = $diskPath ?? session(self::SESSION_PATH);
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'No Excel file found. Upload a file first.',
            ]);
        }

        $parsed = $this->parseFile(Storage::disk('local')->path($path), $defaultCompanyId, $defaultBorder);

        return [
            'original_name' => session(self::SESSION_NAME),
            'ready' => $parsed['ready'],
            'duplicates' => $parsed['duplicates'],
            'skipped' => $parsed['skipped'],
            'rows' => array_slice($parsed['rows'], 0, 80),
        ];
    }

    /**
     * @return array{imported: int, duplicates: int, skipped: int}
     */
    public function confirm(
        User $actor,
        ?int $defaultCompanyId = null,
        ?string $defaultBorder = null,
        ?string $saleState = null
    ): array {
        $path = session(self::SESSION_PATH);
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'No Excel file found. Upload a file first.',
            ]);
        }

        if (! $defaultCompanyId) {
            throw ValidationException::withMessages([
                'company_id' => 'Select a default company for imported cars.',
            ]);
        }

        $state = IranCarSaleState::tryFrom($saleState ?? session(self::SESSION_SALE_STATE, IranCarSaleState::Unsold->value))
            ?? IranCarSaleState::Unsold;

        $parsed = $this->parseFile(Storage::disk('local')->path($path), $defaultCompanyId, $defaultBorder);

        return DB::transaction(function () use ($parsed, $actor, $defaultCompanyId, $state): array {
            $imported = 0;
            $duplicates = 0;
            $skipped = 0;

            foreach ($parsed['rows'] as $row) {
                if ($row['status'] === 'duplicate') {
                    $duplicates++;

                    continue;
                }

                if ($row['status'] !== 'ready') {
                    $skipped++;

                    continue;
                }

                $amount = (float) $row['total_amount'];
                $payload = [
                    'company_id' => $row['company_id'] ?? $defaultCompanyId,
                    'border' => $row['border'],
                    'vin' => $row['vin'],
                    'model_name' => $row['model_name'],
                    'year' => $row['year'],
                    'color' => $row['color'],
                    'total_amount' => $amount,
                    'sale_state' => $state->value,
                    'notes' => null,
                ];

                if ($state === IranCarSaleState::Sold) {
                    $payload['sale_price'] = $amount;
                    $payload['sold_at'] = now()->toDateString();
                }

                $this->iranCarService->create($payload, $actor);

                $imported++;
            }

            session()->forget([self::SESSION_PATH, self::SESSION_NAME, self::SESSION_SALE_STATE]);

            return [
                'imported' => $imported,
                'duplicates' => $duplicates,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * @return array{ready: int, duplicates: int, skipped: int, rows: list<array<string, mixed>>}
     */
    private function parseFile(string $absolutePath, ?int $defaultCompanyId, ?string $defaultBorder): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheetByName('Sorted Inventory') ?? $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        $columns = null;
        $currentBorder = IranBorder::tryFrom($defaultBorder ?? '') ?? IranBorder::tryFromHeader($defaultBorder);
        $seenInFile = [];
        $existingVins = IranCar::query()->pluck('vin')->flip()->all();
        $preview = [];
        $ready = 0;
        $duplicates = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $cells = array_map(static fn ($value) => trim((string) ($value ?? '')), $row);

            if ($this->isEmptyRow($cells)) {
                continue;
            }

            if ($this->isHeaderRow($cells)) {
                $columns = $this->mapColumns($cells);

                continue;
            }

            $headerBorder = $this->detectBorderRow($cells);
            if ($headerBorder) {
                $currentBorder = $headerBorder;

                continue;
            }

            if ($columns === null) {
                $columns = [
                    'model' => 1,
                    'year' => 2,
                    'color' => 3,
                    'vin' => 4,
                    'price' => 5,
                ];
            }

            $vin = $this->iranCarService->normalizeVin($this->cell($cells, $columns['vin'] ?? null));
            $model = $this->cell($cells, $columns['model'] ?? null);
            $year = $this->parseYear($this->cell($cells, $columns['year'] ?? null));
            $color = $this->nullable($this->cell($cells, $columns['color'] ?? null));
            $price = $this->parseAmount($this->cell($cells, $columns['price'] ?? null));

            if ($vin === '' || strlen($vin) < 8) {
                $skipped++;
                $preview[] = $this->previewRow($rowNumber, 'skipped', $currentBorder, $model, $year, $color, $vin, $price, $defaultCompanyId, 'Missing VIN');

                continue;
            }

            if ($model === '') {
                $skipped++;
                $preview[] = $this->previewRow($rowNumber, 'skipped', $currentBorder, $model, $year, $color, $vin, $price, $defaultCompanyId, 'Missing model');

                continue;
            }

            if (! $currentBorder) {
                $skipped++;
                $preview[] = $this->previewRow($rowNumber, 'skipped', null, $model, $year, $color, $vin, $price, $defaultCompanyId, 'Missing border');

                continue;
            }

            if (isset($seenInFile[$vin]) || isset($existingVins[$vin])) {
                $duplicates++;
                $preview[] = $this->previewRow($rowNumber, 'duplicate', $currentBorder, $model, $year, $color, $vin, $price, $defaultCompanyId, 'Duplicate VIN');
                $seenInFile[$vin] = true;

                continue;
            }

            $seenInFile[$vin] = true;
            $ready++;
            $preview[] = $this->previewRow($rowNumber, 'ready', $currentBorder, $model, $year, $color, $vin, $price, $defaultCompanyId, null);
        }

        return [
            'ready' => $ready,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'rows' => $preview,
        ];
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            $value = strtolower($cell);
            if (in_array($value, ['vin', 'vin number', 'vehicle model', 'car name', 'price ($)', 'price($)', 'price'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $cells
     */
    private function detectBorderRow(array $cells): ?IranBorder
    {
        $nonEmpty = array_values(array_filter($cells, static fn (string $cell) => $cell !== ''));
        if ($nonEmpty === [] || count($nonEmpty) > 2) {
            return null;
        }

        foreach ($nonEmpty as $text) {
            $border = IranBorder::tryFromHeader($text);
            if ($border) {
                return $border;
            }
        }

        return null;
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
            $value = str_replace(['($)', '$'], '', $value);
            $value = trim($value);

            if (in_array($value, ['vehicle model', 'car name', 'car'], true)) {
                $map['model'] = $index;
            } elseif ($value === 'year') {
                $map['year'] = $index;
            } elseif ($value === 'model' && ! isset($map['year'])) {
                $map['year'] = $index;
            } elseif ($value === 'color') {
                $map['color'] = $index;
            } elseif (in_array($value, ['vin', 'vin number'], true)) {
                $map['vin'] = $index;
            } elseif (str_starts_with($value, 'price')) {
                $map['price'] = $index;
            }
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

    private function parseYear(string $value): ?int
    {
        if (preg_match('/(19|20)\d{2}/', $value, $matches) === 1) {
            return (int) $matches[0];
        }

        return null;
    }

    private function parseAmount(string $value): float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        return $clean === '' || $clean === '-' ? 0.0 : round((float) $clean, 2);
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

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewRow(
        int $rowNumber,
        string $status,
        ?IranBorder $border,
        string $model,
        ?int $year,
        ?string $color,
        string $vin,
        float $price,
        ?int $companyId,
        ?string $reason
    ): array {
        return [
            'row_number' => $rowNumber,
            'status' => $status,
            'reason' => $reason,
            'border' => $border?->value,
            'border_label' => $border?->label(),
            'model_name' => $model,
            'year' => $year,
            'color' => $color,
            'vin' => $vin,
            'total_amount' => number_format($price, 2, '.', ''),
            'company_id' => $companyId,
        ];
    }
}
