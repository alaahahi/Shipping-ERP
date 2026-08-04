<?php

namespace App\Services;

use App\Enums\DubaiEntryKind;
use App\Models\DubaiAccountEntry;
use App\Models\DubaiPartner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DubaiAccountService
{
    /**
     * @param  array{search?: string|null, active?: string|null}  $filters
     */
    public function paginatePartners(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = DubaiPartner::query()->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        if ($filters['active'] === '1') {
            $query->where('is_active', true);
        } elseif ($filters['active'] === '0') {
            $query->where('is_active', false);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     name: string,
     *     contact_name?: string|null,
     *     contact_phone?: string|null,
     *     notes?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function createPartner(array $data): DubaiPartner
    {
        return DubaiPartner::query()->create([
            'name' => trim($data['name']),
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array{
     *     name: string,
     *     contact_name?: string|null,
     *     contact_phone?: string|null,
     *     notes?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function updatePartner(DubaiPartner $partner, array $data): DubaiPartner
    {
        $partner->update([
            'name' => trim($data['name']),
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? $partner->is_active,
        ]);

        return $partner->fresh();
    }

    public function deletePartner(DubaiPartner $partner): void
    {
        if ($partner->entries()->exists()) {
            throw ValidationException::withMessages([
                'partner' => 'Cannot delete a partner that has account entries.',
            ]);
        }

        $partner->delete();
    }

    /**
     * @return array{
     *     currency: string,
     *     open_balance: string,
     *     total_debit: string,
     *     total_credit: string,
     *     movements: list<array<string, mixed>>
     * }
     */
    public function statement(DubaiPartner $partner): array
    {
        $entries = DubaiAccountEntry::query()
            ->withCount('cars')
            ->where('dubai_partner_id', $partner->id)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $movements = [];

        foreach ($entries as $entry) {
            $debit = round((float) $entry->debit, 2);
            $credit = round((float) $entry->credit, 2);
            $totalDebit = round($totalDebit + $debit, 2);
            $totalCredit = round($totalCredit + $credit, 2);
            $running = round($running + $debit - $credit, 2);

            $movements[] = [
                'id' => $entry->id,
                'date' => $entry->entry_date?->format('Y-m-d'),
                'doc_no' => $entry->doc_no,
                'entry_kind' => $entry->entry_kind?->value,
                'entry_kind_label' => $entry->entry_kind?->label(),
                'transport_qty' => $this->num($entry->transport_qty),
                'transport_rate' => $this->num($entry->transport_rate, 4),
                'transport_total' => $this->num($entry->transport_total),
                'forklift_qty' => $this->num($entry->forklift_qty),
                'forklift_rate' => $this->num($entry->forklift_rate, 4),
                'forklift_total' => $this->num($entry->forklift_total),
                'total_debit' => $this->num($entry->total_debit),
                'debit' => number_format($debit, 2, '.', ''),
                'credit' => number_format($credit, 2, '.', ''),
                'usd_amount' => $entry->usd_amount !== null ? $this->num($entry->usd_amount) : null,
                'balance' => number_format($running, 2, '.', ''),
                'notes' => $entry->notes,
                'cars_count' => $entry->cars_count,
                'can_import_cars' => $entry->entry_kind === DubaiEntryKind::Shipment,
            ];
        }

        return [
            'currency' => 'AED',
            'open_balance' => number_format($running, 2, '.', ''),
            'total_debit' => number_format($totalDebit, 2, '.', ''),
            'total_credit' => number_format($totalCredit, 2, '.', ''),
            'movements' => $movements,
        ];
    }

    public function openBalance(DubaiPartner $partner): string
    {
        $debit = (float) DubaiAccountEntry::query()
            ->where('dubai_partner_id', $partner->id)
            ->sum('debit');
        $credit = (float) DubaiAccountEntry::query()
            ->where('dubai_partner_id', $partner->id)
            ->sum('credit');

        return number_format(round($debit - $credit, 2), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEntry(DubaiPartner $partner, array $data): DubaiAccountEntry
    {
        $payload = $this->normalizeEntryPayload($data);

        return $partner->entries()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEntry(DubaiAccountEntry $entry, array $data): DubaiAccountEntry
    {
        $entry->update($this->normalizeEntryPayload($data));

        return $entry->fresh();
    }

    public function deleteEntry(DubaiAccountEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $entry->cars()->delete();
            $entry->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function transformPartner(DubaiPartner $partner, bool $withBalance = true): array
    {
        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'contact_name' => $partner->contact_name,
            'contact_phone' => $partner->contact_phone,
            'notes' => $partner->notes,
            'is_active' => $partner->is_active,
            'open_balance' => $withBalance ? $this->openBalance($partner) : null,
            'currency' => 'AED',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeEntryPayload(array $data): array
    {
        $kind = DubaiEntryKind::from($data['entry_kind'] ?? DubaiEntryKind::Misc->value);

        $transportQty = $this->nullableFloat($data['transport_qty'] ?? null);
        $transportRate = $this->nullableFloat($data['transport_rate'] ?? null);
        $transportTotal = $this->nullableFloat($data['transport_total'] ?? null);
        if ($transportTotal === null && $transportQty !== null && $transportRate !== null) {
            $transportTotal = round($transportQty * $transportRate, 2);
        }

        $forkliftQty = $this->nullableFloat($data['forklift_qty'] ?? null);
        $forkliftRate = $this->nullableFloat($data['forklift_rate'] ?? null) ?? 50.0;
        $forkliftTotal = $this->nullableFloat($data['forklift_total'] ?? null);
        if ($forkliftTotal === null && $forkliftQty !== null) {
            $forkliftTotal = round($forkliftQty * $forkliftRate, 2);
        }

        $totalDebit = $this->nullableFloat($data['total_debit'] ?? null);
        if ($totalDebit === null) {
            $totalDebit = round(($transportTotal ?? 0) + ($forkliftTotal ?? 0), 2);
            if ($totalDebit <= 0) {
                $totalDebit = null;
            }
        }

        $debit = round((float) ($data['debit'] ?? 0), 2);
        $credit = round((float) ($data['credit'] ?? 0), 2);

        if ($kind === DubaiEntryKind::Shipment && $debit <= 0 && $totalDebit !== null) {
            $debit = $totalDebit;
        }

        if ($debit < 0 || $credit < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Debit and credit must be zero or positive.',
            ]);
        }

        if ($debit <= 0 && $credit <= 0) {
            throw ValidationException::withMessages([
                'debit' => 'Enter a debit or credit amount.',
            ]);
        }

        return [
            'entry_date' => $data['entry_date'],
            'doc_no' => $data['doc_no'] ?? null,
            'entry_kind' => $kind,
            'currency' => $data['currency'] ?? 'AED',
            'transport_qty' => $transportQty,
            'transport_rate' => $transportRate,
            'transport_total' => $transportTotal,
            'forklift_qty' => $forkliftQty,
            'forklift_rate' => $forkliftQty !== null ? $forkliftRate : null,
            'forklift_total' => $forkliftTotal,
            'total_debit' => $totalDebit,
            'debit' => $debit,
            'credit' => $credit,
            'usd_amount' => $this->nullableFloat($data['usd_amount'] ?? null),
            'notes' => $data['notes'] ?? null,
            'ship_id' => $data['ship_id'] ?? null,
            'voyage_id' => $data['voyage_id'] ?? null,
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 4);
    }

    private function num(mixed $value, int $decimals = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $decimals, '.', '');
    }
}
