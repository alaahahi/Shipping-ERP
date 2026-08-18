<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\SettingKey;
use App\Models\Account;
use Illuminate\Validation\ValidationException;

class LandTripCashAccountService
{
    public function __construct(
        private readonly SettingService $settings
    ) {}

    public const MISSING_MESSAGE = 'Set the land-trips cash account (قاسة) in Settings before posting.';

    /**
     * @return list<array{id: int, code: string, name: string, label: string}>
     */
    public function options(): array
    {
        return $this->eligibleQuery()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => $account->code.' — '.$account->name,
            ])
            ->all();
    }

    /**
     * @return array{id: int, code: string, name: string, label: string}|null
     */
    public function payload(): ?array
    {
        $account = $this->configuredAccount();
        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'label' => $account->code.' — '.$account->name,
        ];
    }

    public function configuredAccountId(): ?int
    {
        $raw = trim($this->settings->get(SettingKey::LandTripsCashAccountId));

        if ($raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    public function resolve(): Account
    {
        $accountId = $this->configuredAccountId();

        if (! $accountId) {
            throw ValidationException::withMessages([
                'cash_account_id' => self::MISSING_MESSAGE,
            ]);
        }

        $account = $this->eligibleQuery()->whereKey($accountId)->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'cash_account_id' => self::MISSING_MESSAGE,
            ]);
        }

        return $account;
    }

    public function configuredAccount(): ?Account
    {
        $accountId = $this->configuredAccountId();
        if (! $accountId) {
            return null;
        }

        return $this->eligibleQuery()->whereKey($accountId)->first();
    }

    private function eligibleQuery()
    {
        $headerIds = Account::query()
            ->whereIn('code', ['1000', '1600', '1660'])
            ->pluck('id')
            ->all();

        $receivableParentIds = Account::query()
            ->whereIn('code', ['1600', '1660'])
            ->pluck('id')
            ->all();

        return Account::query()
            ->where('is_active', true)
            ->where('type', AccountType::Asset->value)
            ->where('currency', Currency::USD->value)
            ->when($headerIds !== [], fn ($query) => $query->whereNotIn('id', $headerIds))
            ->when($receivableParentIds !== [], function ($query) use ($receivableParentIds): void {
                $query->where(function ($inner) use ($receivableParentIds): void {
                    $inner->whereNull('parent_id')->orWhereNotIn('parent_id', $receivableParentIds);
                });
            });
    }
}
