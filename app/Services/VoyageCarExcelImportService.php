<?php

namespace App\Services;

use App\Models\Voyage;
use App\Models\VoyageCar;
use App\Models\VoyageCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VoyageCarExcelImportService
{
    public function __construct(
        private readonly VoyageCarService $voyageCarService
    ) {}

    /**
     * Store uploaded Excel and attach metadata to the voyage company.
     *
     * @return array{path: string, original_name: string}
     */
    public function storeUpload(Voyage $voyage, VoyageCompany $company, UploadedFile $file): array
    {
        $this->assertImportable($voyage, $company);

        $path = $file->store("voyage-imports/{$voyage->id}/{$company->id}", 'local');

        $company->update([
            'excel_file_path' => $path,
            'excel_original_name' => $file->getClientOriginalName(),
            'excel_uploaded_at' => now(),
        ]);

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     valid: int,
     *     duplicates: int,
     *     skipped: int,
     *     total_data_rows: int,
     *     original_name: string|null
     * }
     */
    public function preview(Voyage $voyage, VoyageCompany $company, ?string $diskPath = null): array
    {
        $this->assertImportable($voyage, $company);

        $path = $diskPath ?? $company->excel_file_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'No Excel file found for this company. Upload a file first.',
            ]);
        }

        [$rows, $headerIndex] = $this->loadRows(Storage::disk('local')->path($path));

        $valid = 0;
        $duplicates = 0;
        $skipped = 0;
        $previewRows = [];
        $seenInFile = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $payload = $this->mapRow($rows[$i] ?? [], $company->company_name);

            if ($payload === null) {
                $skipped++;
                continue;
            }

            $status = 'ready';
            $chassis = $payload['chassis_no'];

            if ($chassis) {
                if (isset($seenInFile[$chassis]) || $this->chassisExists($voyage->id, $chassis)) {
                    $status = 'duplicate';
                    $duplicates++;
                } else {
                    $seenInFile[$chassis] = true;
                    $valid++;
                }
            } else {
                $valid++;
            }

            if (count($previewRows) < 40) {
                $previewRows[] = [
                    'row_number' => $i + 1,
                    'status' => $status,
                    ...$payload,
                ];
            }
        }

        return [
            'rows' => $previewRows,
            'valid' => $valid,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'total_data_rows' => max(0, count($rows) - $headerIndex - 1),
            'original_name' => $company->excel_original_name,
        ];
    }

    /**
     * @return array{imported: int, skipped: int, duplicates: int, errors: list<string>}
     */
    public function import(Voyage $voyage, VoyageCompany $company, UploadedFile $file): array
    {
        $stored = $this->storeUpload($voyage, $company, $file);

        return $this->importFromStoredPath($voyage, $company->fresh(), $stored['path']);
    }

    /**
     * @return array{imported: int, skipped: int, duplicates: int, errors: list<string>}
     */
    public function importFromStoredPath(Voyage $voyage, VoyageCompany $company, ?string $path = null): array
    {
        $this->assertImportable($voyage, $company);

        $path ??= $company->excel_file_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'Excel file is missing. Upload again.',
            ]);
        }

        [$rows, $headerIndex] = $this->loadRows(Storage::disk('local')->path($path));

        $imported = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];

        DB::transaction(function () use (
            $voyage,
            $company,
            $rows,
            $headerIndex,
            &$imported,
            &$skipped,
            &$duplicates,
            &$errors
        ): void {
            for ($i = $headerIndex + 1; $i < count($rows); $i++) {
                $payload = $this->mapRow($rows[$i] ?? [], $company->company_name);

                if ($payload === null) {
                    $skipped++;
                    continue;
                }

                if ($payload['chassis_no'] && $this->chassisExists($voyage->id, $payload['chassis_no'])) {
                    $duplicates++;
                    continue;
                }

                try {
                    $this->voyageCarService->create($voyage, [
                        ...$payload,
                        'voyage_company_id' => $company->id,
                        'row_number' => $i + 1,
                    ]);
                    $imported++;
                } catch (\Throwable $exception) {
                    $errors[] = 'Row '.($i + 1).': '.$exception->getMessage();
                    if (count($errors) >= 20) {
                        break;
                    }
                }
            }

            $company->update([
                'excel_imported_count' => $imported,
                'excel_uploaded_at' => $company->excel_uploaded_at ?? now(),
            ]);
        });

        return compact('imported', 'skipped', 'duplicates', 'errors');
    }

    /**
     * @return array{0: list<list<mixed>>, 1: int}
     */
    private function loadRows(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headerIndex = $this->findHeaderRow($rows);

        if ($headerIndex === null) {
            throw ValidationException::withMessages([
                'file' => 'Could not find Excel header row (expected S.NO and WEIGHT columns).',
            ]);
        }

        return [$rows, $headerIndex];
    }

    private function assertImportable(Voyage $voyage, VoyageCompany $company): void
    {
        if ($company->voyage_id !== $voyage->id) {
            throw ValidationException::withMessages([
                'voyage_company_id' => 'Selected company does not belong to this voyage.',
            ]);
        }

        if (! $voyage->isEditable()) {
            throw ValidationException::withMessages([
                'voyage' => 'Closed voyages cannot import cars.',
            ]);
        }
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function findHeaderRow(array $rows): ?int
    {
        $limit = min(count($rows), 40);

        for ($i = 0; $i < $limit; $i++) {
            $a = strtoupper(trim((string) ($rows[$i][0] ?? '')));
            $b = strtoupper(trim((string) ($rows[$i][1] ?? '')));

            if (str_contains($a, 'S.NO') || $a === 'SNO' || $a === 'S NO') {
                if (str_contains($b, 'WEIGHT') || $b === '') {
                    return $i;
                }
            }

            if (str_contains($a, 'S.NO') && str_contains(strtoupper(implode(' ', $rows[$i] ?? [])), 'WEIGHT')) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     * @return array{chassis_no: ?string, consignee_name: string, shipper_name: ?string, description: ?string, weight: ?float, code: ?string}|null
     */
    private function mapRow(array $row, string $companyName): ?array
    {
        $weight = $this->clean($row[1] ?? null);
        $shipper = $this->clean($row[2] ?? null) ?: $companyName;
        $description = $this->clean($row[3] ?? null);
        $chassis = strtoupper((string) ($this->clean($row[4] ?? null) ?? ''));
        $consignee = $this->clean($row[5] ?? null);
        $code = $this->clean($row[7] ?? null);

        if ($chassis === '' && $consignee === null && $description === null && $weight === null) {
            return null;
        }

        if ($consignee === null || $consignee === '') {
            return null;
        }

        if ($this->isQuoteOnly($consignee)) {
            return null;
        }

        return [
            'chassis_no' => $chassis !== '' ? $chassis : null,
            'consignee_name' => $consignee,
            'shipper_name' => $shipper,
            'description' => $description,
            'weight' => $this->toDecimal($weight),
            'code' => $code,
        ];
    }

    private function chassisExists(int $voyageId, string $chassis): bool
    {
        return VoyageCar::query()
            ->where('voyage_id', $voyageId)
            ->where('chassis_no', $chassis)
            ->exists();
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function isQuoteOnly(string $value): bool
    {
        return in_array(strtoupper(trim($value)), ['QUOTE', 'QUOTATION', '-'], true);
    }

    private function toDecimal(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace([',', ' '], ['', ''], $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
