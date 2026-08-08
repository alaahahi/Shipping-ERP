<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\ShipPartnerContribution;
use App\Models\User;
use App\Notifications\AccountingPostedNotification;
use App\Services\Concerns\ResolvesExpensePaymentAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipPartnerContributionPostingService
{
    use ResolvesExpensePaymentAccounts;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly ShipExpensePostingService $shipExpensePostingService,
        private readonly NotificationDispatchService $notificationDispatchService
    ) {}

    public function post(ShipPartnerContribution $row, int $paymentAccountId, User $actor): ShipPartnerContribution
    {
        $row->loadMissing(['ship', 'owner', 'journalEntry']);

        if ($row->isPostedToAccounting()) {
            throw ValidationException::withMessages([
                'contribution' => 'This partner payment is already posted to accounting.',
            ]);
        }

        $currency = $row->currency;
        if (! in_array($currency, [Currency::USD, Currency::AED], true)) {
            throw ValidationException::withMessages([
                'currency' => 'Only USD and AED partner payments can be posted.',
            ]);
        }

        $amount = round((float) $row->amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $cashAccount = $this->resolvePaymentAccount($paymentAccountId, $currency);
        $clearing = $this->shipExpensePostingService->resolvePartnerClearingAccount($currency);
        $memo = trim(implode(' · ', array_filter([
            $row->owner?->name,
            $row->description,
            $row->reference,
        ])));

        $description = sprintf(
            'Ship partner payment — %s — %s (%s)',
            $row->ship?->name ?? '#'.$row->ship_id,
            $row->owner?->name ?? '#'.$row->owner_id,
            $currency->value
        );

        $row = DB::transaction(function () use (
            $row,
            $actor,
            $currency,
            $cashAccount,
            $clearing,
            $amount,
            $memo,
            $description
        ): ShipPartnerContribution {
            if ($row->journal_entry_id) {
                $row->update(['journal_entry_id' => null]);
            }

            $draft = $this->journalService->createDraft([
                'entry_date' => $row->contribution_date?->toDateString() ?? now()->toDateString(),
                'currency' => $currency->value,
                'reference' => 'SPAY-'.$row->id,
                'description' => $description,
                'lines' => [
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => $memo ?: null,
                    ],
                    [
                        'account_id' => $clearing->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => $memo ?: null,
                        'owner_id' => $row->owner_id,
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);
            $row->update(['journal_entry_id' => $posted->id]);

            return $row->fresh(['journalEntry', 'owner', 'ship']);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                'Ship partner payment posted',
                sprintf(
                    '%s — voucher %s (%s %s).',
                    $row->ship?->name ?? 'Ship',
                    $row->journalEntry?->voucher_number ?? '—',
                    number_format($amount, 2, '.', ''),
                    $currency->value
                ),
                route('journals.show', $row->journal_entry_id),
                $row->journalEntry?->voucher_number
            ),
            $actor->id
        );

        return $row;
    }
}
