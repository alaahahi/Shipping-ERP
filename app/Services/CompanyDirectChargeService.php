<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CompanyDirectChargeStatus;
use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyDirectCharge;
use App\Models\User;
use App\Notifications\AccountingPostedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyDirectChargeService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CompanyReceivableAccountService $companyReceivableAccounts,
        private readonly NotificationDispatchService $notificationDispatchService
    ) {}

    /**
     * @return list<array{id: int, label: string, code: string}>
     */
    public function creditAccountOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where('currency', Currency::USD->value)
            ->whereIn('type', [
                AccountType::Revenue->value,
                AccountType::Liability->value,
                AccountType::Equity->value,
            ])
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'label' => $account->code.' — '.$account->name,
            ])
            ->all();
    }

    public function defaultCreditAccountId(): ?int
    {
        $id = Account::query()
            ->where('code', '4100')
            ->where('is_active', true)
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @param  array{
     *     charge_date: string,
     *     amount: float|int|string,
     *     currency?: string,
     *     credit_account_id?: int|null,
     *     reference?: string|null,
     *     description: string
     * }  $data
     */
    public function createAndPost(Company $company, array $data, User $actor): CompanyDirectCharge
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $currency = Currency::from($data['currency'] ?? Currency::USD->value);
        if ($currency !== Currency::USD) {
            throw ValidationException::withMessages([
                'currency' => 'Company direct receivables currently support USD only.',
            ]);
        }

        $receivable = $this->companyReceivableAccounts->resolveFor($company);
        $creditAccount = $this->resolveCreditAccount(
            isset($data['credit_account_id']) ? (int) $data['credit_account_id'] : null,
            $currency
        );

        $description = trim((string) $data['description']);
        $memo = sprintf('Direct receivable — %s', $company->name);

        $charge = DB::transaction(function () use (
            $company,
            $data,
            $actor,
            $amount,
            $currency,
            $receivable,
            $creditAccount,
            $description,
            $memo
        ): CompanyDirectCharge {
            $charge = CompanyDirectCharge::query()->create([
                'voucher_number' => $this->nextVoucherNumber(),
                'company_id' => $company->id,
                'charge_date' => $data['charge_date'],
                'currency' => $currency,
                'amount' => $amount,
                'credit_account_id' => $creditAccount->id,
                'reference' => $data['reference'] ?? null,
                'description' => $description,
                'status' => CompanyDirectChargeStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $draft = $this->journalService->createDraft([
                'entry_date' => $charge->charge_date?->toDateString() ?? now()->toDateString(),
                'currency' => $currency->value,
                'reference' => $charge->voucher_number,
                'description' => $description !== '' ? $description : $memo,
                'lines' => [
                    [
                        'account_id' => $receivable->id,
                        'company_id' => $company->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => $memo,
                    ],
                    [
                        'account_id' => $creditAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => sprintf('%s · %s', $creditAccount->code, $creditAccount->name),
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);

            $charge->update([
                'status' => CompanyDirectChargeStatus::Posted,
                'journal_entry_id' => $posted->id,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            return $charge->fresh(['journalEntry', 'creditAccount', 'company', 'creator', 'poster']);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                'Direct receivable posted',
                sprintf(
                    '%s — %s (%s %s).',
                    $charge->voucher_number,
                    $charge->journalEntry?->voucher_number ?? '—',
                    number_format($amount, 2, '.', ''),
                    $currency->value
                ),
                route('journals.show', $charge->journal_entry_id),
                $charge->journalEntry?->voucher_number
            ),
            $actor->id
        );

        return $charge;
    }

    public function nextVoucherNumber(): string
    {
        $prefix = 'CDC-'.now()->format('Ym').'-';
        $latest = CompanyDirectCharge::query()
            ->withTrashed()
            ->where('voucher_number', 'like', $prefix.'%')
            ->orderByDesc('voucher_number')
            ->value('voucher_number');

        $sequence = 1;
        if ($latest) {
            $sequence = ((int) substr($latest, -4)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function resolveCreditAccount(?int $accountId, Currency $currency): Account
    {
        if (! $accountId) {
            $accountId = $this->defaultCreditAccountId();
        }

        if (! $accountId) {
            throw ValidationException::withMessages([
                'credit_account_id' => 'Shipping revenue 4100 is missing. Seed the chart of accounts.',
            ]);
        }

        $account = Account::query()->whereKey($accountId)->first();

        if (! $account || ! $account->is_active) {
            throw ValidationException::withMessages([
                'credit_account_id' => 'Credit account not found or inactive.',
            ]);
        }

        if ($account->currency !== $currency) {
            throw ValidationException::withMessages([
                'credit_account_id' => "Credit account must use {$currency->value}.",
            ]);
        }

        $allowed = [
            AccountType::Revenue,
            AccountType::Liability,
            AccountType::Equity,
        ];

        if (! in_array($account->type, $allowed, true)) {
            throw ValidationException::withMessages([
                'credit_account_id' => 'Credit account must be revenue, liability, or equity.',
            ]);
        }

        return $account;
    }
}
