<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IranCarReceivableAccountService
{
    public const CONTROL_CODE = '1660';

    public function controlAccount(): Account
    {
        $account = Account::query()
            ->where('code', self::CONTROL_CODE)
            ->where('currency', Currency::USD->value)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'account' => 'Iran Cars Receivable 1660 is missing. Seed the chart of accounts.',
            ]);
        }

        return $account;
    }

    public function revenueAccount(): Account
    {
        $account = Account::query()
            ->where('code', '4300')
            ->where('currency', Currency::USD->value)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'account' => 'Iran Cars Revenue 4300 is missing. Seed the chart of accounts.',
            ]);
        }

        return $account;
    }

    public function ensureFor(Company $company): Account
    {
        return DB::transaction(function () use ($company): Account {
            $control = $this->controlAccount();
            $account = $this->findExisting($company, $control);

            if ($account) {
                $this->syncAccount($account, $company, $control);
            } else {
                $account = Account::query()->create([
                    'code' => $this->nextCode($company),
                    'name' => $this->accountName($company),
                    'type' => AccountType::Asset->value,
                    'currency' => Currency::USD->value,
                    'parent_id' => $control->id,
                    'is_system' => false,
                    'is_active' => $company->is_active,
                    'description' => 'Iran cars accounts receivable',
                ]);
            }

            if ((int) $company->iran_ar_account_id !== (int) $account->id) {
                $company->forceFill(['iran_ar_account_id' => $account->id])->save();
            }

            return $account->fresh();
        });
    }

    public function resolveFor(Company $company): Account
    {
        $company->loadMissing('iranArAccount');

        if ($company->iranArAccount && $company->iranArAccount->is_active) {
            return $company->iranArAccount;
        }

        return $this->ensureFor($company);
    }

    public function syncIfLinked(Company $company): void
    {
        if (! $company->iran_ar_account_id) {
            return;
        }

        $this->ensureFor($company);
    }

    private function findExisting(Company $company, Account $control): ?Account
    {
        if ($company->iran_ar_account_id) {
            $linked = Account::query()->withTrashed()->find($company->iran_ar_account_id);
            if ($linked) {
                if ($linked->trashed()) {
                    $linked->restore();
                }

                return $linked;
            }
        }

        return Account::query()
            ->where('code', $this->preferredCode($company))
            ->where('parent_id', $control->id)
            ->first();
    }

    private function syncAccount(Account $account, Company $company, Account $control): void
    {
        $account->fill([
            'name' => $this->accountName($company),
            'type' => AccountType::Asset->value,
            'currency' => Currency::USD->value,
            'parent_id' => $control->id,
            'is_active' => $company->is_active || $account->journalLines()->exists(),
            'description' => 'Iran cars accounts receivable',
        ]);

        if ($account->isDirty()) {
            $account->save();
        }
    }

    private function accountName(Company $company): string
    {
        return $company->name.' — Iran cars';
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
