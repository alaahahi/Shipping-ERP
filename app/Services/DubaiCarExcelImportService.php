<?php

namespace App\Services;

use App\Enums\DubaiEntryKind;
use App\Models\DubaiAccountEntry;
use App\Models\DubaiCar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DubaiCarExcelImportService
{
    /**
     * @return array{path: string, original_name: string}
     */
    public function storeUpload(DubaiAccountEntry $entry, UploadedFile $file): array
    {
        $this->assertImportable($entry);

        $path = $file->store("dubai-imports/{$entry->dubai_partner_id}/{$entry->id}", 'local');

        $entry->update([
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
    public function preview(DubaiAccountEntry $entry, ?string $diskPath = null): array
    {
        $this->assertImportable($entry);

        $path = $diskPath ?? $entry->excel_file_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'file' => 'No Excel file found. Upload a file first.',
            ]);
        }

        [$rows, $headerIndex] = $this->loadRows(Storage::disk('local')->path($path));

        $valid = 0;
        $duplicates = 0;
        $skipped = 0;
        $previewRows = [];
        $seenInFile = [];

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $payload = $this->mapRow($rows[$i] ?? []);

            if ($payload === null) {
                $skipped++;
                continue;
            }

            $status = 'ready';
            $chassis = $payload['chassis_no'];

            if ($chassis) {
                if (isset($seenInFile[$chassis]) || $this->chassisExists($entry->id, $chassis)) {
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
            'original_name' => $entry->excel_original_name,
        ];
    }

    /**
     * @return array{imported: int, skipped: int, duplicates: int, errors: list<string>}
     */
    public function import(DubaiAccountEntry $entry, UploadedFile $file): array
    {
        $stored = $this->storeUpload($entry, $file);

        return $this->importFromStoredPath($entry->fresh(), $stored['path']);
    }

    /**
     * @return array{imported: int, skipped: int, duplicates: int, errors: list<string>}
     */
    public function importFromStoredPath(DubaiAccountEntry $entry, ?string $path = null): array
    {
        $this->assertImportable($entry);

        $path ??= $entry->excel_file_path;
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
            $entry,
            $rows,
            $headerIndex,
            &$imported,
            &$skipped,
            &$duplicates,
            &$errors
        ): void {
            for ($i = $headerIndex + 1; $i < count($rows); $i++) {
                $payload = $this->mapRow($rows[$i] ?? []);

                if ($payload === null) {
                    $skipped++;
                    continue;
                }

                if ($payload['chassis_no'] && $this->chassisExists($entry->id, $payload['chassis_no'])) {
                    $duplicates++;
                    continue;
                }

                try {
                    DubaiCar::query()->create([
                        ...$payload,
                        'dubai_account_entry_id' => $entry->id,
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
        });

        return compact('imported', 'skipped', 'duplicates', 'errors');
    }

    private function assertImportable(DubaiAccountEntry $entry): void
    {
        if ($entry->entry_kind !== DubaiEntryKind::Shipment) {
            throw ValidationException::withMessages([
                'entry' => 'Cars can only be imported on shipment entries.',
            ]);
        }
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
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $consignee = trim((string) ($row[5] ?? ''));
        $upper = strtoupper($consignee);

        if ($consignee === '' || in_array($upper, ['QUOTE', 'QUOTATION', '-'], true)) {
            return null;
        }

        $chassis = trim((string) ($row[4] ?? ''));
        $weight = $row[1] ?? null;

        return [
            'weight' => is_numeric($weight) ? round((float) $weight, 3) : null,
            'shipper_name' => trim((string) ($row[2] ?? '')) ?: null,
            'description' => trim((string) ($row[3] ?? '')) ?: null,
            'chassis_no' => $chassis !== '' ? $chassis : null,
            'consignee_name' => $consignee,
            'code' => trim((string) ($row[7] ?? '')) ?: null,
        ];
    }

    private function chassisExists(int $entryId, string $chassis): bool
    {
        return DubaiCar::query()
            ->where('dubai_account_entry_id', $entryId)
            ->where('chassis_no', $chassis)
            ->exists();
    }
}
