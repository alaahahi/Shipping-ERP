<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Owner;
use App\Models\Ship;
use App\Models\ShipExpense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipExpenseService
{
    /**
     * @param  array{
     *     expense_type: string,
     *     amount: float|int|string,
     *     currency?: string,
     *     expense_date: string,
     *     vendor?: string|null,
     *     reference?: string|null,
     *     notes?: string|null,
     *     created_by?: int|null,
     *     paid_by_owner_id?: int|null
     * }  $data
     */
    public function create(Ship $ship, array $data): ShipExpense
    {
        $paidByOwnerId = $this->normalizePaidByOwnerId($ship, $data['paid_by_owner_id'] ?? null);

        return DB::transaction(fn (): ShipExpense => $ship->expenses()->create([
            'expense_type' => $data['expense_type'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? Currency::USD->value,
            'expense_date' => $data['expense_date'],
            'vendor' => $data['vendor'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'paid_by_owner_id' => $paidByOwnerId,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return int
     */
    public function createMany(Ship $ship, array $rows, ?int $createdBy = null): int
    {
        return DB::transaction(function () use ($ship, $rows, $createdBy): int {
            $count = 0;
            foreach ($rows as $row) {
                if (($row['amount'] ?? 0) <= 0 || empty($row['expense_date'])) {
                    continue;
                }
                $this->create($ship, [
                    ...$row,
                    'created_by' => $createdBy,
                ]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * @param  array{
     *     expense_type: string,
     *     amount: float|int|string,
     *     currency?: string,
     *     expense_date: string,
     *     vendor?: string|null,
     *     reference?: string|null,
     *     notes?: string|null,
     *     paid_by_owner_id?: int|null
     * }  $data
     */
    public function update(ShipExpense $expense, array $data): ShipExpense
    {
        $expense->loadMissing(['journalEntry', 'ship']);
        $this->assertNotPosted($expense);
        $paidByOwnerId = $this->normalizePaidByOwnerId($expense->ship, $data['paid_by_owner_id'] ?? null);

        return DB::transaction(function () use ($expense, $data, $paidByOwnerId): ShipExpense {
            $expense->update([
                'expense_type' => $data['expense_type'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? Currency::USD->value,
                'expense_date' => $data['expense_date'],
                'vendor' => $data['vendor'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_by_owner_id' => $paidByOwnerId,
            ]);

            return $expense->fresh(['paidByOwner']);
        });
    }

    public function delete(ShipExpense $expense): void
    {
        $expense->loadMissing('journalEntry');
        $this->assertNotPosted($expense);
        $expense->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(ShipExpense $expense): array
    {
        $expense->loadMissing(['journalEntry', 'paidByOwner:id,name']);
        $posted = $expense->isPostedToAccounting();

        return [
            'id' => $expense->id,
            'ship_id' => $expense->ship_id,
            'paid_by_owner_id' => $expense->paid_by_owner_id,
            'paid_by_owner_name' => $expense->paidByOwner?->name,
            'expense_type' => $expense->expense_type->value,
            'expense_type_label' => $expense->expense_type->label(),
            'amount' => number_format((float) $expense->amount, 2, '.', ''),
            'currency' => $expense->currency->value,
            'expense_date' => $expense->expense_date?->format('Y-m-d'),
            'vendor' => $expense->vendor,
            'reference' => $expense->reference,
            'notes' => $expense->notes,
            'is_posted' => $posted,
            'can_post' => in_array($expense->currency->value, [Currency::USD->value, Currency::AED->value], true)
                && ! $posted,
            'journal_entry_id' => $expense->journalEntry?->id,
            'journal_voucher' => $expense->journalEntry?->voucher_number,
            'journal_status' => $expense->journalEntry?->status?->value,
        ];
    }

    /**
     * @return list<array{currency: string, total: string}>
     */
    public function totalsByCurrency(Ship $ship): array
    {
        return $ship->expenses()
            ->selectRaw('currency, COALESCE(SUM(amount), 0) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency instanceof Currency
                    ? $row->currency->value
                    : (string) $row->currency,
                'total' => number_format((float) $row->total, 2, '.', ''),
            ])
            ->all();
    }

    private function assertNotPosted(ShipExpense $expense): void
    {
        if ($expense->isPostedToAccounting()) {
            throw ValidationException::withMessages([
                'expense' => 'Posted ship expenses cannot be edited or deleted. Void the journal first.',
            ]);
        }
    }

    private function normalizePaidByOwnerId(Ship $ship, mixed $ownerId): ?int
    {
        if ($ownerId === null || $ownerId === '' || (int) $ownerId === 0) {
            return null;
        }

        $ownerId = (int) $ownerId;
        $onShip = $ship->ownerships()->where('owner_id', $ownerId)->exists();

        if (! $onShip) {
            throw ValidationException::withMessages([
                'paid_by_owner_id' => 'Selected payer is not an owner of this ship.',
            ]);
        }

        if (! Owner::query()->whereKey($ownerId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'paid_by_owner_id' => 'Selected payer is inactive.',
            ]);
        }

        return $ownerId;
    }
}
