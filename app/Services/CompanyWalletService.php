<?php

namespace App\Services;

use App\Enums\CompanyWalletEntryType;
use App\Enums\Currency;
use App\Models\Company;
use App\Models\CompanyWalletEntry;
use App\Models\User;
use App\Support\AmountInWords;
use App\Support\ApplicationTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompanyWalletService
{
    /**
     * @return array{
     *     balances: list<array{currency: string, balance: string}>,
     *     entries: list<array<string, mixed>>,
     *     currencies: list<string>
     * }
     */
    public function payload(Company $company): array
    {
        $entries = CompanyWalletEntry::query()
            ->where('company_id', $company->id)
            ->with('creator:id,name')
            ->latest('id')
            ->limit(100)
            ->get();

        return [
            'balances' => $this->balances($company),
            'entries' => $entries->map(fn (CompanyWalletEntry $entry) => $this->transform($entry))->values()->all(),
            'currencies' => Currency::values(),
        ];
    }

    /**
     * @return list<array{currency: string, balance: string}>
     */
    public function balances(Company $company): array
    {
        $totals = CompanyWalletEntry::query()
            ->where('company_id', $company->id)
            ->selectRaw("currency, COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as balance")
            ->groupBy('currency')
            ->pluck('balance', 'currency');

        if ($totals->isEmpty()) {
            return [[
                'currency' => Currency::USD->value,
                'balance' => number_format(0, 2, '.', ''),
            ]];
        }

        return $totals
            ->sortKeys()
            ->map(fn ($balance, $currency) => [
                'currency' => (string) $currency,
                'balance' => number_format((float) $balance, 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    public function balanceFor(Company $company, Currency $currency): float
    {
        $balance = CompanyWalletEntry::query()
            ->where('company_id', $company->id)
            ->where('currency', $currency->value)
            ->lockForUpdate()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) as balance")
            ->value('balance');

        return round((float) $balance, 2);
    }

    /**
     * @param  array{type: string, amount: float|string, currency: string, notes?: string|null}  $data
     */
    public function create(Company $company, array $data, User $actor): CompanyWalletEntry
    {
        $type = CompanyWalletEntryType::from($data['type']);
        $currency = Currency::from($data['currency']);
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter an amount greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($company, $actor, $type, $currency, $amount, $data): CompanyWalletEntry {
            if ($type === CompanyWalletEntryType::Withdraw) {
                $available = $this->balanceFor($company, $currency);
                if ($amount > $available) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient company wallet balance.',
                    ]);
                }
            }

            $entry = CompanyWalletEntry::query()->create([
                'company_id' => $company->id,
                'voucher_number' => $this->nextVoucherNumber($company),
                'type' => $type,
                'amount' => $amount,
                'currency' => $currency,
                'notes' => $this->nullableString($data['notes'] ?? null),
                'created_by' => $actor->id,
            ]);

            Log::info('Company wallet entry recorded.', [
                'company_id' => $company->id,
                'entry_id' => $entry->id,
                'type' => $type->value,
                'amount' => $amount,
                'currency' => $currency->value,
                'user_id' => $actor->id,
                'accounting' => false,
            ]);

            return $entry->load('creator:id,name');
        });
    }

    public function delete(Company $company, CompanyWalletEntry $entry, User $actor): void
    {
        if ((int) $entry->company_id !== (int) $company->id) {
            abort(404);
        }

        DB::transaction(function () use ($company, $entry, $actor): void {
            $locked = CompanyWalletEntry::query()
                ->whereKey($entry->id)
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currency = $locked->currency instanceof Currency
                ? $locked->currency
                : Currency::from((string) $locked->currency);

            $current = $this->balanceFor($company, $currency);
            $signed = (float) $locked->amount * $locked->type->signedMultiplier();
            $remaining = round($current - $signed, 2);

            if ($remaining < 0) {
                throw ValidationException::withMessages([
                    'entry' => 'Cannot delete this deposit while later withdrawals still depend on it.',
                ]);
            }

            $locked->delete();

            Log::info('Company wallet entry deleted.', [
                'company_id' => $company->id,
                'entry_id' => $locked->id,
                'voucher_number' => $locked->voucher_number,
                'type' => $locked->type->value,
                'amount' => (string) $locked->amount,
                'currency' => $currency->value,
                'user_id' => $actor->id,
                'accounting' => false,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(CompanyWalletEntry $entry): array
    {
        $amount = (string) $entry->amount;
        $currency = $entry->currency instanceof Currency ? $entry->currency->value : (string) $entry->currency;
        $words = AmountInWords::both($amount, $currency);

        return [
            'id' => $entry->id,
            'voucher_number' => $entry->voucher_number,
            'type' => $entry->type->value,
            'amount' => $amount,
            'currency' => $currency,
            'notes' => $entry->notes,
            'created_at' => ApplicationTimezone::formatDateTime($entry->created_at),
            'created_by_name' => $entry->creator?->name,
            'amount_words_ar' => $words['arabic'],
            'amount_words_ckb' => $words['kurdish'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function printPayload(Company $company, CompanyWalletEntry $entry): array
    {
        if ((int) $entry->company_id !== (int) $company->id) {
            abort(404);
        }

        $entry->loadMissing('creator:id,name');

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'entry' => $this->transform($entry),
            'printed_at' => ApplicationTimezone::formatNow(),
        ];
    }

    private function nextVoucherNumber(Company $company): string
    {
        $prefix = 'W-'.$company->id.'-';
        $latest = CompanyWalletEntry::query()
            ->withTrashed()
            ->where('company_id', $company->id)
            ->where('voucher_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('voucher_number');

        $next = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
