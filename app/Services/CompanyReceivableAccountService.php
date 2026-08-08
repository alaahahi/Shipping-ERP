<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyReceivableAccountService
{
    public const CONTROL_CODE = '1600';

    public function controlAccount(): Account
    {
        $account = Account::query()
            ->where('code', self::CONTROL_CODE)
            ->where('currency', Currency::USD->value)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'account' => 'Accounts Receivable 1600 is missing. Seed the chart of accounts.',
            ]);
        }

        return $account;
    }

    public function ensureFor(Company $company): Account
    {
        return DB::transaction(function () use ($company): Account {
            $control = $this->controlAccount();
            $account = $this->findExisting($company);

            if ($account) {
                $this->syncAccount($account, $company, $control);
            } else {
                $account = Account::query()->create([
                    'code' => $this->nextCode($company),
                    'name' => $company->name,
                    'type' => AccountType::Asset->value,
                    'currency' => Currency::USD->value,
                    'parent_id' => $control->id,
                    'accountable_type' => $company->getMorphClass(),
                    'accountable_id' => $company->id,
                    'is_system' => false,
                    'is_active' => $company->is_active,
                    'description' => 'Company accounts receivable',
                ]);
            }

            if ((int) $company->ar_account_id !== (int) $account->id) {
                $company->forceFill(['ar_account_id' => $account->id])->save();
            }

            return $account->fresh();
        });
    }

    public function resolveFor(Company $company): Account
    {
        $company->loadMissing('arAccount');

        if ($company->arAccount && $company->arAccount->is_active) {
            return $company->arAccount;
        }

        return $this->ensureFor($company);
    }

    /**
     * Control 1600 plus subsidiary company AR accounts (for ledgers / historical lines).
     *
     * @return list<int>
     */
    public function receivableAccountIds(?Company $company = null): array
    {
        $control = $this->controlAccount();

        $ids = Account::query()
            ->where(function ($query) use ($control): void {
                $query->whereKey($control->id)->orWhere('parent_id', $control->id);
            })
            ->pluck('id')
            ->all();

        if ($company?->ar_account_id) {
            $ids[] = (int) $company->ar_account_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function isCompanyReceivable(Account $account): bool
    {
        if ($account->accountable_type === (new Company)->getMorphClass()) {
            return true;
        }

        return Company::query()->where('ar_account_id', $account->id)->exists();
    }

    private function findExisting(Company $company): ?Account
    {
        if ($company->ar_account_id) {
            $linked = Account::query()->withTrashed()->find($company->ar_account_id);
            if ($linked) {
                if ($linked->trashed()) {
                    $linked->restore();
                }

                return $linked;
            }
        }

        $byMorph = Account::query()
            ->where('accountable_type', $company->getMorphClass())
            ->where('accountable_id', $company->id)
            ->first();

        if ($byMorph) {
            return $byMorph;
        }

        return Account::query()->where('code', $this->preferredCode($company))->first();
    }

    private function syncAccount(Account $account, Company $company, Account $control): void
    {
        $account->fill([
            'name' => $company->name,
            'type' => AccountType::Asset->value,
            'currency' => Currency::USD->value,
            'parent_id' => $control->id,
            'accountable_type' => $company->getMorphClass(),
            'accountable_id' => $company->id,
            'is_active' => $company->is_active || $account->journalLines()->exists(),
        ]);

        if ($account->isDirty()) {
            $account->save();
        }
    }

    private function preferredCode(Company $company): string
    {
        return self::CONTROL_CODE.'-'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT);
    }

    private function nextCode(Company $company): string
    {
        $base = $this->preferredCode($company);

        if (! Account::query()->withTrashed()->where('code', $base)->exists()) {
            return $base;
        }

        $suffix = 2;
        while (Account::query()->withTrashed()->where('code', $base.'-'.$suffix)->exists()) {
            $suffix++;
        }

        return $base.'-'.$suffix;
    }
}
