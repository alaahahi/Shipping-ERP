<?php

namespace App\Services;

use App\Enums\DubaiEntryKind;
use App\Models\DubaiPartner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DubaiSoaExcelImportService
{
    public function __construct(
        private readonly DubaiAccountService $dubaiAccountService
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function import(DubaiPartner $partner, UploadedFile $file, bool $replace = false): array
    {
        $rows = $this->loadRows($file->getRealPath() ?: $file->getPathname());
        $headerIndex = $this->findHeaderRow($rows);

        if ($headerIndex === null) {
            throw ValidationException::withMessages([
                'file' => 'Could not find SOA header row (expected DATE and NO_DOC columns).',
            ]);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($partner, $rows, $headerIndex, $replace, &$imported, &$skipped, &$errors): void {
            if ($replace) {
                $partner->entries()->each(function ($entry): void {
                    $entry->cars()->delete();
                    $entry->delete();
                });
            }

            for ($i = $headerIndex + 1; $i < count($rows); $i++) {
                $payload = $this->mapRow($rows[$i] ?? []);

                if ($payload === null) {
                    $skipped++;
                    continue;
                }

                try {
                    $this->dubaiAccountService->createEntry($partner, $payload);
                    $imported++;
                } catch (\Throwable $exception) {
                    $errors[] = 'Row '.($i + 1).': '.$exception->getMessage();
                    if (count($errors) >= 25) {
                        break;
                    }
                }
            }
        });

        return compact('imported', 'skipped', 'errors');
    }

    /**
     * @return list<list<mixed>>
     */
    private function loadRows(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
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

            if ($a === 'DATE' && (str_contains($b, 'NO_DOC') || str_contains($b, 'NO DOC') || $b === 'DOC')) {
                return $i;
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
        $date = $this->parseDate($row[0] ?? null);
        $docNo = trim((string) ($row[1] ?? ''));

        if ($date === null && $docNo === '') {
            return null;
        }

        if ($date === null) {
            return null;
        }

        $transportQty = $this->numericOrNull($row[2] ?? null);
        $transportRate = $this->numericOrNull($row[3] ?? null);
        $transportTotal = $this->numericOrNull($row[4] ?? null);
        $forkliftQty = $this->numericOrNull($row[5] ?? null);
        $forkliftRate = $this->numericOrNull($row[6] ?? null) ?? 50.0;
        $forkliftTotal = $this->numericOrNull($row[7] ?? null);
        $totalDebit = $this->numericOrNull($row[8] ?? null);
        $debitCol = $this->numericOrNull($row[9] ?? null);
        $creditCol = $this->numericOrNull($row[10] ?? null);

        $forkliftNote = null;
        if ($forkliftQty === null && is_string($row[5] ?? null) && trim((string) $row[5]) !== '') {
            $forkliftNote = trim((string) $row[5]);
        }

        if ($transportTotal === null && $transportQty !== null && $transportRate !== null) {
            $transportTotal = round($transportQty * $transportRate, 2);
        }

        if ($forkliftTotal === null && $forkliftQty !== null) {
            $forkliftTotal = round($forkliftQty * $forkliftRate, 2);
        }

        if ($totalDebit === null && ($transportTotal !== null || $forkliftTotal !== null)) {
            $totalDebit = round(($transportTotal ?? 0) + ($forkliftTotal ?? 0), 2);
        }

        $usdAmount = $this->extractUsdAmount($docNo);
        $hasShipmentMetrics = ($transportQty !== null && $transportQty > 0)
            || ($forkliftQty !== null && $forkliftQty > 0)
            || ($totalDebit !== null && $totalDebit > 0 && $creditCol === null);

        $kind = DubaiEntryKind::Misc;
        $debit = 0.0;
        $credit = 0.0;

        if ($hasShipmentMetrics && ($creditCol === null || $creditCol <= 0)) {
            $kind = DubaiEntryKind::Shipment;
            $debit = $totalDebit ?? $debitCol ?? 0.0;
        } elseif ($creditCol !== null && $creditCol > 0) {
            $kind = DubaiEntryKind::Transfer;
            $credit = $creditCol;
            if ($debitCol !== null && $debitCol > 0) {
                $debit = $debitCol;
            }
        } elseif ($debitCol !== null && $debitCol > 0) {
            $kind = DubaiEntryKind::Misc;
            $debit = $debitCol;
        } elseif ($totalDebit !== null && $totalDebit > 0) {
            $kind = DubaiEntryKind::Misc;
            $debit = $totalDebit;
        } else {
            return null;
        }

        if ($debit <= 0 && $credit <= 0) {
            return null;
        }

        $notes = $forkliftNote;
        if (is_string($row[4] ?? null) && ! is_numeric($row[4]) && trim((string) $row[4]) !== '') {
            $notes = trim(($notes ? $notes.' · ' : '').(string) $row[4]);
        }

        return [
            'entry_date' => $date,
            'doc_no' => $docNo !== '' ? $docNo : null,
            'entry_kind' => $kind->value,
            'currency' => 'AED',
            'transport_qty' => $kind === DubaiEntryKind::Shipment ? $transportQty : null,
            'transport_rate' => $kind === DubaiEntryKind::Shipment ? $transportRate : null,
            'transport_total' => $kind === DubaiEntryKind::Shipment ? $transportTotal : null,
            'forklift_qty' => $kind === DubaiEntryKind::Shipment ? $forkliftQty : null,
            'forklift_rate' => $kind === DubaiEntryKind::Shipment && $forkliftQty !== null ? $forkliftRate : null,
            'forklift_total' => $kind === DubaiEntryKind::Shipment ? $forkliftTotal : null,
            'total_debit' => $kind === DubaiEntryKind::Shipment ? $totalDebit : null,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'usd_amount' => $usdAmount,
            'notes' => $notes,
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 4);
    }

    private function extractUsdAmount(string $docNo): ?float
    {
        if (preg_match('/\$\s*([0-9]+(?:\.[0-9]+)?)/', $docNo, $matches)) {
            return round((float) $matches[1], 2);
        }

        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*\$/', $docNo, $matches)) {
            return round((float) $matches[1], 2);
        }

        return null;
    }
}
