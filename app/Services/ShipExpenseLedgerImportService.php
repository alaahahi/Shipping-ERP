<?php

namespace App\Services;

use App\Enums\ShipExpenseType;
use App\Models\Ship;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ShipExpenseLedgerImportService
{

    /**
     * Parse Excel into list-entry rows. Nothing is saved until the user confirms the list.
     *
     * @return array{rows: list<array<string, mixed>>, skipped: int}
     */
    public function previewExpenses(Ship $ship, UploadedFile $file, string $currency = 'USD', ?int $paidByOwnerId = null): array
    {
        $parsed = $this->loadRows($file);
        $ownersByName = $this->ownerNameMap($ship);
        $rows = [];
        $skipped = 0;

        foreach ($parsed as $payload) {
            if ($payload === null) {
                $skipped++;
                continue;
            }

            $rows[] = [
                'expense_type' => $this->inferExpenseType((string) ($payload['description'] ?? '')),
                'amount' => $payload['amount'],
                'currency' => $currency,
                'expense_date' => $payload['date'],
                'vendor' => $payload['description'] ?? '',
                'reference' => $payload['reference'] ?? '',
                'paid_by_owner_id' => $this->matchOwnerId($payload['payer'] ?? null, $ownersByName) ?? $paidByOwnerId,
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'No valid expense rows found (need a date and amount).',
            ]);
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, skipped: int}
     */
    public function previewContributions(
        Ship $ship,
        UploadedFile $file,
        int $ownerId,
        string $currency = 'USD'
    ): array {
        $this->assertOwnerOnShip($ship, $ownerId);
        $parsed = $this->loadRows($file);
        $rows = [];
        $skipped = 0;

        foreach ($parsed as $payload) {
            if ($payload === null) {
                $skipped++;
                continue;
            }

            $rows[] = [
                'owner_id' => $ownerId,
                'contribution_date' => $payload['date'],
                'amount' => $payload['amount'],
                'currency' => $currency,
                'description' => $payload['description'] ?? '',
                'reference' => $payload['reference'] ?? '',
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'No valid payment rows found (need a date and amount).',
            ]);
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * @return list<array{date: string, description: ?string, amount: float, reference: ?string, payer: ?string}|null>
     */
    private function loadRows(UploadedFile $file): array
    {
        $absolute = $file->getRealPath() ?: $file->getPathname();
        $spreadsheet = IOFactory::load($absolute);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($sheetRows === []) {
            throw ValidationException::withMessages([
                'file' => 'The Excel file is empty.',
            ]);
        }

        $headerIndex = $this->findHeaderRow($sheetRows);
        $map = $headerIndex === null
            ? ['date' => 0, 'description' => 1, 'amount' => 2, 'reference' => 3, 'payer' => 4]
            : $this->mapHeader($sheetRows[$headerIndex]);

        if ($map['date'] === null || $map['amount'] === null) {
            throw ValidationException::withMessages([
                'file' => 'Could not find Date and Amount columns. Use Date / Reason / Amount.',
            ]);
        }

        $start = $headerIndex === null ? 0 : $headerIndex + 1;
        $parsed = [];

        for ($i = $start; $i < count($sheetRows); $i++) {
            $parsed[] = $this->mapRow($sheetRows[$i] ?? [], $map);
        }

        if (collect($parsed)->filter()->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'No valid rows found (need a date and amount).',
            ]);
        }

        return $parsed;
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function findHeaderRow(array $rows): ?int
    {
        $limit = min(count($rows), 20);

        for ($i = 0; $i < $limit; $i++) {
            $joined = mb_strtolower(implode(' ', array_map(fn ($cell) => (string) $cell, $rows[$i] ?? [])));
            if (
                (str_contains($joined, 'date') || str_contains($joined, 'تاريخ') || str_contains($joined, 'بەروار'))
                && (str_contains($joined, 'amount') || str_contains($joined, 'مبلغ') || str_contains($joined, 'پێداویستی') || str_contains($joined, 'requirement'))
            ) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $header
     * @return array{date: int|null, description: int|null, amount: int|null, reference: int|null, payer: int|null}
     */
    private function mapHeader(array $header): array
    {
        $map = ['date' => null, 'description' => null, 'amount' => null, 'reference' => null, 'payer' => null];

        foreach ($header as $index => $cell) {
            $label = mb_strtolower(trim((string) $cell));
            if ($label === '') {
                continue;
            }

            if ($map['date'] === null && (str_contains($label, 'date') || str_contains($label, 'تاريخ') || str_contains($label, 'بەروار'))) {
                $map['date'] = $index;
            } elseif ($map['amount'] === null && (
                str_contains($label, 'amount')
                || str_contains($label, 'مبلغ')
                || str_contains($label, 'پێداویستی')
                || str_contains($label, 'requirement')
                || str_contains($label, 'واسلى')
            )) {
                $map['amount'] = $index;
            } elseif ($map['payer'] === null && (
                str_contains($label, 'paid by')
                || str_contains($label, 'payer')
                || str_contains($label, 'partner')
                || str_contains($label, 'owner')
                || str_contains($label, 'دفعها')
                || str_contains($label, 'دافع')
                || str_contains($label, 'شريك')
                || str_contains($label, 'هاوبەش')
            )) {
                $map['payer'] = $index;
            } elseif ($map['reference'] === null && (str_contains($label, 'ref') || str_contains($label, 'رقم') || str_contains($label, 'no'))) {
                $map['reference'] = $index;
            } elseif ($map['description'] === null && (
                str_contains($label, 'reason')
                || str_contains($label, 'سبب')
                || str_contains($label, 'سەبەب')
                || str_contains($label, 'desc')
                || str_contains($label, 'وصف')
            )) {
                $map['description'] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array{date: int|null, description: int|null, amount: int|null, reference: int|null, payer?: int|null}  $map
     * @return array{date: string, description: ?string, amount: float, reference: ?string, payer: ?string}|null
     */
    private function mapRow(array $row, array $map): ?array
    {
        $date = $this->parseDate($row[$map['date']] ?? null);
        $amount = $this->numericOrNull($row[$map['amount']] ?? null);
        $description = trim((string) ($row[$map['description'] ?? -1] ?? ''));
        $reference = trim((string) ($row[$map['reference'] ?? -1] ?? ''));
        $payer = trim((string) ($row[$map['payer'] ?? -1] ?? ''));

        if ($date === null && ($amount === null || $amount <= 0) && $description === '') {
            return null;
        }

        if ($date === null || $amount === null || $amount <= 0) {
            return null;
        }

        return [
            'date' => $date,
            'description' => $description !== '' ? $description : null,
            'amount' => $amount,
            'reference' => $reference !== '' ? $reference : null,
            'payer' => $payer !== '' ? $payer : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function ownerNameMap(Ship $ship): array
    {
        $ship->loadMissing('ownerships.owner');
        $map = [];

        foreach ($ship->ownerships as $ownership) {
            $name = trim((string) $ownership->owner?->name);
            if ($name === '') {
                continue;
            }
            $map[mb_strtolower($name)] = (int) $ownership->owner_id;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $ownersByName
     */
    private function matchOwnerId(?string $name, array $ownersByName): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $key = mb_strtolower(trim($name));

        return $ownersByName[$key] ?? null;
    }

    private function inferExpenseType(string $text): string
    {
        $t = mb_strtolower($text);

        return match (true) {
            str_contains($t, 'fuel') || str_contains($t, 'مازوت') || str_contains($t, 'وقود') || str_contains($t, 'بنزین') => ShipExpenseType::Fuel->value,
            str_contains($t, 'salary') || str_contains($t, 'راتب') || str_contains($t, 'مووچە') || str_contains($t, 'موچه') => ShipExpenseType::Salary->value,
            str_contains($t, 'food') || str_contains($t, 'طعام') || str_contains($t, 'خواردن') => ShipExpenseType::Food->value,
            str_contains($t, 'rent') || str_contains($t, 'إيجار') || str_contains($t, 'كرێ') || str_contains($t, 'باخیرە') || str_contains($t, 'باخيرة') => ShipExpenseType::Rent->value,
            str_contains($t, 'transfer') || str_contains($t, 'حوالة') || str_contains($t, 'گواستن') => ShipExpenseType::Transfer->value,
            str_contains($t, 'crew') || str_contains($t, 'طاقم') => ShipExpenseType::Crew->value,
            str_contains($t, 'insurance') || str_contains($t, 'تأمين') => ShipExpenseType::Insurance->value,
            str_contains($t, 'drydock') || str_contains($t, 'حوض') => ShipExpenseType::Drydock->value,
            str_contains($t, 'maintenance') || str_contains($t, 'صيانة') => ShipExpenseType::Maintenance->value,
            default => ShipExpenseType::Other->value,
        };
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

        $normalized = str_replace(['/', '.'], '-', trim((string) $value));
        $timestamp = strtotime($normalized);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', ' ', '$'], '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function assertOwnerOnShip(Ship $ship, int $ownerId): void
    {
        if (! $ship->ownerships()->where('owner_id', $ownerId)->exists()) {
            throw ValidationException::withMessages([
                'owner_id' => 'Selected partner is not an owner of this ship.',
            ]);
        }
    }
}
