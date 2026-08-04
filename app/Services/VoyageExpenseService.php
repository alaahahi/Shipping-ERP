<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\VoyageExpenseType;
use App\Models\Voyage;
use App\Models\VoyageExpense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageExpenseService
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
    public function create(Voyage $voyage, array $data): VoyageExpense
    {
        $this->assertVoyageEditable($voyage);

        return DB::transaction(fn (): VoyageExpense => $voyage->expenses()->create([
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
    public function update(VoyageExpense $expense, array $data): VoyageExpense
    {
        $expense->loadMissing(['voyage', 'journalEntry']);
        $this->assertVoyageEditable($expense->voyage);
        $this->assertNotPosted($expense);

        return DB::transaction(function () use ($expense, $data): VoyageExpense {
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

    public function delete(VoyageExpense $expense): void
    {
        $expense->loadMissing(['voyage', 'journalEntry']);
        $this->assertVoyageEditable($expense->voyage);
        $this->assertNotPosted($expense);
        $expense->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(VoyageExpense $expense): array
    {
        $expense->loadMissing('journalEntry');
        $journal = $expense->journalEntry;
        $posted = $expense->isPostedToAccounting();

        return [
            'id' => $expense->id,
            'voyage_id' => $expense->voyage_id,
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
            'journal_entry_id' => $journal?->id,
            'journal_voucher' => $journal?->voucher_number,
            'journal_status' => $journal?->status?->value,
        ];
    }

    private function assertNotPosted(VoyageExpense $expense): void
    {
        if ($expense->isPostedToAccounting()) {
            throw ValidationException::withMessages([
                'expense' => 'Posted expenses cannot be edited or deleted. Void the journal first.',
            ]);
        }
    }

    /**
     * Totals by currency for a voyage (operational only — not journal balances).
     *
     * @return list<array{currency: string, total: string}>
     */
    public function totalsByCurrency(Voyage $voyage): array
    {
        return $voyage->expenses()
            ->selectRaw('currency, COALESCE(SUM(amount), 0) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(function ($row) {
                $currency = $row->currency;

                return [
                    'currency' => $currency instanceof Currency
                        ? $currency->value
                        : (string) $currency,
                    'total' => number_format((float) $row->total, 2, '.', ''),
                ];
            })
            ->all();
    }

    private function assertVoyageEditable(?Voyage $voyage): void
    {
        if (! $voyage || ! $voyage->isEditable()) {
            throw ValidationException::withMessages([
                'voyage' => 'Closed voyages cannot accept expense changes.',
            ]);
        }
    }
}
