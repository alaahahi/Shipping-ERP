<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\ShipExpense;
use App\Models\User;
use App\Notifications\AccountingPostedNotification;
use App\Services\Concerns\ResolvesExpensePaymentAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipExpensePostingService
{
    use ResolvesExpensePaymentAccounts;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly NotificationDispatchService $notificationDispatchService
    ) {}

    public function post(ShipExpense $expense, int $paymentAccountId, User $actor): ShipExpense
    {
        $expense->loadMissing(['ship', 'journalEntry']);

        if ($expense->journal_entry_id && $expense->journalEntry && ! $expense->journalEntry->isVoid()) {
            throw ValidationException::withMessages([
                'expense' => 'This ship expense is already posted to accounting.',
            ]);
        }

        $currency = $expense->currency;
        if (! in_array($currency, [Currency::USD, Currency::AED], true)) {
            throw ValidationException::withMessages([
                'currency' => 'Only USD and AED expenses can be posted to the chart of accounts.',
            ]);
        }

        $expenseAccount = $this->resolveExpenseAccountByCode(
            $currency === Currency::AED ? '5200' : '5110',
            $currency
        );
        $paymentAccount = $this->resolvePaymentAccount($paymentAccountId, $currency);
        $amount = round((float) $expense->amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Expense amount must be greater than zero.',
            ]);
        }

        $memo = trim(implode(' · ', array_filter([
            $expense->expense_type->label(),
            $expense->vendor,
            $expense->reference,
        ])));

        $description = sprintf(
            'Ship expense — %s — %s (%s)',
            $expense->ship?->name ?? '#'.$expense->ship_id,
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
        ): ShipExpense {
            if ($expense->journal_entry_id) {
                $expense->update(['journal_entry_id' => null]);
            }

            $draft = $this->journalService->createDraft([
                'entry_date' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
                'currency' => $currency->value,
                'reference' => 'SEXP-'.$expense->id,
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

            return $expense->fresh(['journalEntry', 'ship']);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                'Ship expense posted',
                sprintf(
                    '%s — voucher %s (%s %s).',
                    $expense->ship?->name ?? 'Ship',
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
