<?php

namespace App\Services;

use App\Enums\Currency;
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
     *     created_by?: int|null
     * }  $data
     */
    public function create(Ship $ship, array $data): ShipExpense
    {
        return DB::transaction(fn (): ShipExpense => $ship->expenses()->create([
            'expense_type' => $data['expense_type'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? Currency::USD->value,
            'expense_date' => $data['expense_date'],
            'vendor' => $data['vendor'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
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
     *     notes?: string|null
     * }  $data
     */
    public function update(ShipExpense $expense, array $data): ShipExpense
    {
        $expense->loadMissing('journalEntry');
        $this->assertNotPosted($expense);

        return DB::transaction(function () use ($expense, $data): ShipExpense {
            $expense->update([
                'expense_type' => $data['expense_type'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? Currency::USD->value,
                'expense_date' => $data['expense_date'],
                'vendor' => $data['vendor'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $expense->fresh();
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
        $expense->loadMissing('journalEntry');
        $posted = $expense->isPostedToAccounting();

        return [
            'id' => $expense->id,
            'ship_id' => $expense->ship_id,
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
}
