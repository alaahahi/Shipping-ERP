<?php

namespace App\Services\Concerns;

use App\Enums\Currency;
use App\Models\Account;
use Illuminate\Validation\ValidationException;

trait ResolvesExpensePaymentAccounts
{
    /**
     * @return list<array{id: int, label: string, currency: string}>
     */
    public function paymentAccountOptions(string $currency): array
    {
        $codes = match ($currency) {
            Currency::USD->value => ['1100', '1200', '1400', '2100'],
            Currency::AED->value => ['1300', '1500'],
            default => [],
        };

        if ($codes === []) {
            return [];
        }

        return Account::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->where('currency', $currency)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'currency'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'label' => "{$account->code} — {$account->name}",
                'currency' => $account->currency->value,
            ])
            ->all();
    }

    protected function resolvePaymentAccount(int $accountId, Currency $currency): Account
    {
        $account = Account::query()->whereKey($accountId)->first();

        if (! $account || ! $account->is_active) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Payment account not found or inactive.',
            ]);
        }

        if ($account->currency !== $currency) {
            throw ValidationException::withMessages([
                'payment_account_id' => "Payment account must use {$currency->value}.",
            ]);
        }

        $allowed = collect($this->paymentAccountOptions($currency->value))->pluck('id');
        if (! $allowed->contains($account->id)) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Selected account is not a valid payment source for this currency.',
            ]);
        }

        return $account;
    }

    protected function resolveExpenseAccountByCode(string $code, Currency $currency): Account
    {
        $account = Account::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'account' => "System expense account {$code} is missing. Seed the chart of accounts.",
            ]);
        }

        if ($account->currency !== $currency) {
            throw ValidationException::withMessages([
                'account' => "Expense account {$code} currency mismatch.",
            ]);
        }

        return $account;
    }
}
