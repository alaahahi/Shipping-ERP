<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;
use App\Models\Voyage;
use App\Notifications\AccountingPostedNotification;
use App\Services\Concerns\ResolvesExpensePaymentAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageSettlementPostingService
{
    use ResolvesExpensePaymentAccounts;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly VoyageSettlementService $voyageSettlementService,
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly CompanyWhatsappNotificationService $whatsappNotificationService,
        private readonly CompanyReceivableAccountService $companyReceivableAccounts
    ) {}

    /**
     * Recognize voyage shipping revenue: Dr company AR (child of 1600) / Cr Shipping Revenue 4100 (USD).
     */
    public function postRevenue(Voyage $voyage, User $actor): Voyage
    {
        $voyage->loadMissing(['revenueJournalEntry', 'ship:id,name']);

        if ($this->isLinkedAndPosted($voyage->revenue_journal_entry_id, $voyage->revenueJournalEntry)) {
            throw ValidationException::withMessages([
                'revenue' => 'Voyage revenue is already posted to accounting.',
            ]);
        }

        $settlement = $this->voyageSettlementService->forVoyage($voyage);
        $amount = round((float) $settlement['summary']['revenue_usd'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'revenue' => 'Revenue must be greater than zero before posting.',
            ]);
        }

        $companyRows = collect($settlement['companies'] ?? [])
            ->filter(fn (array $row) => round((float) ($row['due_usd'] ?? 0), 2) > 0)
            ->values();

        if ($companyRows->isEmpty()) {
            throw ValidationException::withMessages([
                'revenue' => 'No company dues to post.',
            ]);
        }

        $unlinked = $companyRows->first(fn (array $row) => empty($row['master_company_id']));
        if ($unlinked) {
            throw ValidationException::withMessages([
                'revenue' => 'Every voyage company must be linked to a master company before posting revenue.',
            ]);
        }

        $revenue = $this->resolveExpenseAccountByCode('4100', Currency::USD);
        $companies = Company::query()
            ->whereIn('id', $companyRows->pluck('master_company_id')->map(fn ($id) => (int) $id)->unique()->all())
            ->get()
            ->keyBy('id');

        $description = sprintf(
            'Shipping revenue — voyage %s%s',
            $voyage->voyage_number,
            $voyage->ship?->name ? ' · '.$voyage->ship->name : ''
        );

        $voyage = DB::transaction(function () use (
            $voyage,
            $actor,
            $amount,
            $revenue,
            $description,
            $companyRows,
            $companies
        ): Voyage {
            if ($voyage->revenue_journal_entry_id) {
                $voyage->update(['revenue_journal_entry_id' => null]);
            }

            $lines = $companyRows->map(function (array $row) use ($voyage, $companies): array {
                $company = $companies->get((int) $row['master_company_id']);
                if (! $company) {
                    throw ValidationException::withMessages([
                        'revenue' => 'Every voyage company must be linked to a master company before posting revenue.',
                    ]);
                }

                $receivable = $this->companyReceivableAccounts->resolveFor($company);

                return [
                    'account_id' => $receivable->id,
                    'company_id' => $company->id,
                    'voyage_id' => $voyage->id,
                    'debit' => round((float) $row['due_usd'], 2),
                    'credit' => 0,
                    'memo' => sprintf(
                        'AR — %s · voyage %s',
                        $row['company_name'] ?? $company->name,
                        $voyage->voyage_number
                    ),
                ];
            })->all();

            $lines[] = [
                'account_id' => $revenue->id,
                'debit' => 0,
                'credit' => $amount,
                'memo' => 'Shipping revenue recognized',
            ];

            $draft = $this->journalService->createDraft([
                'entry_date' => $voyage->sailing_date?->toDateString() ?? now()->toDateString(),
                'currency' => Currency::USD->value,
                'reference' => 'VREV-'.$voyage->id,
                'description' => $description,
                'lines' => $lines,
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);
            $voyage->update(['revenue_journal_entry_id' => $posted->id]);

            return $voyage->fresh(['revenueJournalEntry']);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                'Shipping revenue posted',
                sprintf(
                    'Voyage %s — voucher %s (%s USD).',
                    $voyage->voyage_number,
                    $voyage->revenueJournalEntry?->voucher_number ?? '—',
                    number_format($amount, 2, '.', '')
                ),
                route('journals.show', $voyage->revenue_journal_entry_id),
                $voyage->revenueJournalEntry?->voucher_number
            ),
            $actor->id
        );

        foreach ($voyage->companies as $voyageCompany) {
            $row = $companyRows->first(fn (array $r) => (int) $r['master_company_id'] === $voyageCompany->company_id);

            if ($row) {
                $this->whatsappNotificationService->notifyVoyageRevenuePosted(
                    $voyageCompany,
                    $voyage,
                    (float) ($row['due_usd'] ?? 0)
                );
            }
        }

        return $voyage;
    }

    /**
     * Post captain commission: Dr 5310 / Cr payment account (AED).
     */
    public function postCommission(Voyage $voyage, int $paymentAccountId, User $actor): Voyage
    {
        $voyage->loadMissing(['commissionJournalEntry', 'ship:id,name']);

        if ($this->isLinkedAndPosted($voyage->commission_journal_entry_id, $voyage->commissionJournalEntry)) {
            throw ValidationException::withMessages([
                'commission' => 'Captain commission is already posted to accounting.',
            ]);
        }

        $settlement = $this->voyageSettlementService->forVoyage($voyage);
        $amount = round((float) $settlement['summary']['total_captain_commission_aed'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'commission' => 'Commission must be greater than zero before posting.',
            ]);
        }

        $expenseAccount = $this->resolveExpenseAccountByCode('5310', Currency::AED);
        $paymentAccount = $this->resolvePaymentAccount($paymentAccountId, Currency::AED);

        $description = sprintf(
            'Captain commission — voyage %s%s',
            $voyage->voyage_number,
            $voyage->ship?->name ? ' · '.$voyage->ship->name : ''
        );

        $voyage = DB::transaction(function () use (
            $voyage,
            $actor,
            $amount,
            $expenseAccount,
            $paymentAccount,
            $description
        ): Voyage {
            if ($voyage->commission_journal_entry_id) {
                $voyage->update(['commission_journal_entry_id' => null]);
            }

            $draft = $this->journalService->createDraft([
                'entry_date' => $voyage->sailing_date?->toDateString() ?? now()->toDateString(),
                'currency' => Currency::AED->value,
                'reference' => 'VCOM-'.$voyage->id,
                'description' => $description,
                'lines' => [
                    [
                        'account_id' => $expenseAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => 'Captain commission',
                    ],
                    [
                        'account_id' => $paymentAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => 'Commission paid / payable',
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);
            $voyage->update(['commission_journal_entry_id' => $posted->id]);

            return $voyage->fresh(['commissionJournalEntry']);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                'Captain commission posted',
                sprintf(
                    'Voyage %s — voucher %s (%s AED).',
                    $voyage->voyage_number,
                    $voyage->commissionJournalEntry?->voucher_number ?? '—',
                    number_format($amount, 2, '.', '')
                ),
                route('journals.show', $voyage->commission_journal_entry_id),
                $voyage->commissionJournalEntry?->voucher_number
            ),
            $actor->id
        );

        return $voyage;
    }

    /**
     * @return array{
     *     revenue_posted: bool,
     *     revenue_journal_entry_id: int|null,
     *     revenue_voucher: string|null,
     *     can_post_revenue: bool,
     *     commission_posted: bool,
     *     commission_journal_entry_id: int|null,
     *     commission_voucher: string|null,
     *     can_post_commission: bool
     * }
     */
    public function postingStatus(Voyage $voyage, array $settlementSummary): array
    {
        $voyage->loadMissing(['revenueJournalEntry', 'commissionJournalEntry']);

        $revenuePosted = $this->isLinkedAndPosted(
            $voyage->revenue_journal_entry_id,
            $voyage->revenueJournalEntry
        );
        $commissionPosted = $this->isLinkedAndPosted(
            $voyage->commission_journal_entry_id,
            $voyage->commissionJournalEntry
        );

        $revenueAmount = round((float) ($settlementSummary['revenue_usd'] ?? 0), 2);
        $commissionAmount = round((float) ($settlementSummary['total_captain_commission_aed'] ?? 0), 2);

        return [
            'revenue_posted' => $revenuePosted,
            'revenue_journal_entry_id' => $voyage->revenueJournalEntry?->id,
            'revenue_voucher' => $voyage->revenueJournalEntry?->voucher_number,
            'can_post_revenue' => ! $revenuePosted && $revenueAmount > 0,
            'commission_posted' => $commissionPosted,
            'commission_journal_entry_id' => $voyage->commissionJournalEntry?->id,
            'commission_voucher' => $voyage->commissionJournalEntry?->voucher_number,
            'can_post_commission' => ! $commissionPosted && $commissionAmount > 0,
        ];
    }

    private function isLinkedAndPosted(?int $journalId, mixed $journal): bool
    {
        return $journalId !== null
            && $journal
            && ! $journal->isVoid();
    }
}
