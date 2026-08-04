<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\User;
use App\Models\VoyageExpense;
use App\Notifications\AccountingPostedNotification;
use App\Services\Concerns\ResolvesExpensePaymentAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageExpensePostingService
{
    use ResolvesExpensePaymentAccounts;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly NotificationDispatchService $notificationDispatchService
    ) {}

    /**
     * Create a balanced journal (Dr voyage expense / Cr payment) and post it.
     * Balances are derived from posted lines — never updated directly.
     */
    public function post(VoyageExpense $expense, int $paymentAccountId, User $actor): VoyageExpense
    {
        $expense->loadMissing(['voyage.ship', 'journalEntry']);

        if ($expense->journal_entry_id && $expense->journalEntry && ! $expense->journalEntry->isVoid()) {
            throw ValidationException::withMessages([
                'expense' => 'This expense is already posted to accounting.',
            ]);
        }

        $currency = $expense->currency;
        if (! in_array($currency, [Currency::USD, Currency::AED], true)) {
            throw ValidationException::withMessages([
                'currency' => 'Only USD and AED expenses can be posted to the chart of accounts.',
            ]);
        }

        // Voyage ops: 5100 USD. AED voyage costs still map to 5200 until a dedicated AED voyage account exists.
        $expenseAccount = $this->resolveExpenseAccountByCode(
            $currency === Currency::AED ? '5200' : '5100',
            $currency
        );
        $paymentAccount = $this->resolvePaymentAccount($paymentAccountId, $currency);

        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Expense amount must be greater than zero.',
            ]);
        }

        $voyage = $expense->voyage;
        $memo = trim(implode(' · ', array_filter([
            $expense->expense_type->label(),
            $expense->vendor,
            $expense->reference,
        ])));

        $description = sprintf(
            'Voyage expense %s — %s (%s)',
            $voyage?->voyage_number ?? '#'.$expense->voyage_id,
            $expense->expense_type->label(),
            $currency->value
        );

        $expense = DB::transaction(function () use (
            $expense,
            $actor,
            $currency,
            $expenseAccount,
            $paymentAccount,
            $amount,
            $memo,
            $description
        ): VoyageExpense {
            if ($expense->journal_entry_id) {
                $expense->update(['journal_entry_id' => null]);
            }

            $draft = $this->journalService->createDraft([
                'entry_date' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
                'currency' => $currency->value,
                'reference' => 'VEXP-'.$expense->id,
                'description' => $description,
                'lines' => [
                    [
                        'account_id' => $expenseAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => $memo ?: null,
                    ],
                    [
                        'account_id' => $paymentAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => $memo ?: null,
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);
            $expense->update(['journal_entry_id' => $posted->id]);

            return $expense->fresh(['journalEntry', 'voyage']);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                'Voyage expense posted',
                sprintf(
                    '%s — voucher %s (%s %s).',
                    $expense->voyage?->voyage_number ?? 'Voyage',
                    $expense->journalEntry?->voucher_number ?? '—',
                    number_format($amount, 2, '.', ''),
                    $currency->value
                ),
                route('journals.show', $expense->journal_entry_id),
                $expense->journalEntry?->voucher_number
            ),
            $actor->id
        );

        return $expense;
    }
}
