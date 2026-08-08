<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\LandTrip;
use App\Models\User;
use App\Notifications\AccountingPostedNotification;
use App\Services\Concerns\ResolvesExpensePaymentAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandTripPostingService
{
    use ResolvesExpensePaymentAccounts;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly CompanyReceivableAccountService $companyReceivableAccounts
    ) {}

    public function post(LandTrip $trip, User $actor): LandTrip
    {
        $trip->loadMissing(['company:id,name', 'fromCountry:id,name,name_ar', 'toCountry:id,name,name_ar', 'journalEntry']);

        if ($trip->isPosted() && $trip->journalEntry?->isPosted()) {
            throw ValidationException::withMessages([
                'freight' => 'Land trip freight is already posted to accounting.',
            ]);
        }

        $amount = round((float) $trip->freight_amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'freight_amount' => 'Freight amount must be greater than zero before posting.',
            ]);
        }

        if ($trip->currency !== Currency::USD) {
            throw ValidationException::withMessages([
                'currency' => 'Land trip posting currently supports USD only (AR 1600 / revenue 4200).',
            ]);
        }

        if (! $trip->company_id) {
            throw ValidationException::withMessages([
                'company_id' => 'A shipping company is required before posting freight.',
            ]);
        }

        $trip->loadMissing('company');
        if (! $trip->company) {
            throw ValidationException::withMessages([
                'company_id' => 'A shipping company is required before posting freight.',
            ]);
        }

        $receivable = $this->companyReceivableAccounts->resolveFor($trip->company);
        $revenue = $this->resolveExpenseAccountByCode('4200', Currency::USD);

        $description = sprintf(
            'Land transit freight — CMR %s · %s → %s',
            $trip->cmr_number,
            $trip->fromCountry?->localizedName() ?? '—',
            $trip->toCountry?->localizedName() ?? '—'
        );

        $trip = DB::transaction(function () use ($trip, $actor, $amount, $receivable, $revenue, $description): LandTrip {
            if ($trip->journal_entry_id) {
                $trip->update(['journal_entry_id' => null]);
            }

            $draft = $this->journalService->createDraft([
                'entry_date' => $trip->departure_date?->toDateString() ?? now()->toDateString(),
                'currency' => Currency::USD->value,
                'reference' => 'LCMR-'.$trip->id,
                'description' => $description,
                'lines' => [
                    [
                        'account_id' => $receivable->id,
                        'company_id' => $trip->company_id,
                        'voyage_id' => $trip->voyage_id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => sprintf(
                            'AR — %s · CMR %s',
                            $trip->company?->name ?? 'Company',
                            $trip->cmr_number
                        ),
                    ],
                    [
                        'account_id' => $revenue->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => 'Land transit revenue recognized',
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);
            $trip->update(['journal_entry_id' => $posted->id]);

            return $trip->fresh(['journalEntry', 'company:id,name']);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                'Land transit revenue posted',
                sprintf(
                    'CMR %s — voucher %s (%s USD).',
                    $trip->cmr_number,
                    $trip->journalEntry?->voucher_number ?? '—',
                    number_format($amount, 2, '.', '')
                ),
                route('journals.show', $trip->journal_entry_id),
                $trip->journalEntry?->voucher_number
            ),
            $actor->id
        );

        return $trip;
    }
}
