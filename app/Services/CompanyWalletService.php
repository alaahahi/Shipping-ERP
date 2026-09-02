<?php

namespace App\Services;

use App\Enums\CompanyWalletEntryType;
use App\Enums\Currency;
use App\Models\Company;
use App\Models\CompanyWalletEntry;
use App\Models\JournalEntry;
use App\Models\LandTripCar;
use App\Models\User;
use App\Support\AmountInWords;
use App\Support\ApplicationTimezone;
use App\Support\AttachmentMeta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyWalletService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CompanyReceivableAccountService $companyReceivableAccounts,
        private readonly LandTripCashAccountService $cashAccounts,
        private readonly LandDriverPaymentService $driverPayments,
        private readonly LandPaymentChassisService $chassisService
    ) {}

    /**
     * @return array{
     *     balances: list<array{currency: string, balance: string}>,
     *     summary: array{currency: string, cars_count: int, cars_total: string, paid: string, remaining: string},
     *     entries: list<array<string, mixed>>,
     *     currencies: list<string>,
     *     cash_account: array{id: int, code: string, name: string, label: string}|null,
     *     driver_names: list<string>,
     *     driver_payments: list<array<string, mixed>>
     * }
     */
    public function payload(Company $company): array
    {
        $entries = CompanyWalletEntry::query()
            ->where('company_id', $company->id)
            ->with(['creator:id,name', 'journalEntry:id,voucher_number'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $driverPayments = $this->driverPayments->models($company);
        $chassisMap = $this->chassisService->mapForPayables($entries->concat($driverPayments));

        return [
            'balances' => $this->balances($company),
            'summary' => $this->freightSummary($company),
            'entries' => $entries->map(fn (CompanyWalletEntry $entry) => $this->transform(
                $entry,
                $chassisMap[$entry->getMorphClass().':'.$entry->id] ?? []
            ))->values()->all(),
            'currencies' => Currency::values(),
            'cash_account' => $this->cashAccounts->payload(),
            'driver_names' => $this->driverPayments->driverNames(),
            'driver_payments' => $driverPayments->map(fn ($payment) => $this->driverPayments->transform(
                $payment,
                $chassisMap[$payment->getMorphClass().':'.$payment->id] ?? []
            ))->values()->all(),
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

    /**
     * @return array{
     *     currency: string,
     *     cars_count: int,
     *     cars_total: string,
     *     paid: string,
     *     remaining: string
     * }
     */
    public function freightSummary(Company $company): array
    {
        $currency = Currency::USD->value;

        $carsTotal = (float) LandTripCar::query()
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
            ->sum('price');

        $carsCount = (int) LandTripCar::query()
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
            ->count();

        $paid = (float) CompanyWalletEntry::query()
            ->where('company_id', $company->id)
            ->where('currency', $currency)
            ->where('type', CompanyWalletEntryType::Deposit->value)
            ->sum('amount');

        $remaining = round($carsTotal - $paid, 2);

        return [
            'currency' => $currency,
            'cars_count' => $carsCount,
            'cars_total' => number_format($carsTotal, 2, '.', ''),
            'paid' => number_format($paid, 2, '.', ''),
            'remaining' => number_format($remaining, 2, '.', ''),
        ];
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
     * @param  array{type: string, amount: float|string, currency: string, entry_date: string, notes?: string|null}  $data
     */
    public function create(Company $company, array $data, User $actor, ?UploadedFile $attachment = null): CompanyWalletEntry
    {
        $type = CompanyWalletEntryType::from($data['type']);
        $currency = Currency::from($data['currency']);
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter an amount greater than zero.',
            ]);
        }

        if ($currency !== Currency::USD) {
            throw ValidationException::withMessages([
                'currency' => 'Wallet deposits and withdrawals post in USD only (company receivable and cash account).',
            ]);
        }

        $cashAccount = $this->cashAccounts->resolve();
        $receivable = $this->companyReceivableAccounts->resolveFor($company);

        return DB::transaction(function () use (
            $company,
            $actor,
            $type,
            $currency,
            $amount,
            $data,
            $cashAccount,
            $receivable,
            $attachment
        ): CompanyWalletEntry {
            if ($type === CompanyWalletEntryType::Withdraw) {
                $available = $this->balanceFor($company, $currency);
                if ($amount > $available) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient company wallet balance.',
                    ]);
                }
            }

            $notes = $this->nullableString($data['notes'] ?? null);
            $entryDate = (string) $data['entry_date'];
            $entry = CompanyWalletEntry::query()->create([
                'company_id' => $company->id,
                'voucher_number' => $this->nextVoucherNumber($company),
                'type' => $type,
                'amount' => $amount,
                'currency' => $currency,
                'entry_date' => $entryDate,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $isDeposit = $type === CompanyWalletEntryType::Deposit;
            $label = $isDeposit ? 'Wallet deposit' : 'Wallet withdrawal';
            $description = sprintf(
                '%s — %s · %s %s',
                $label,
                $company->name,
                number_format($amount, 2, '.', ''),
                $currency->value
            );
            if ($notes) {
                $description .= ' · '.$notes;
            }

            $cashLine = [
                'account_id' => $cashAccount->id,
                'debit' => $isDeposit ? $amount : 0,
                'credit' => $isDeposit ? 0 : $amount,
                'memo' => sprintf('%s · %s', $cashAccount->code, $cashAccount->name),
            ];
            $arLine = [
                'account_id' => $receivable->id,
                'company_id' => $company->id,
                'debit' => $isDeposit ? 0 : $amount,
                'credit' => $isDeposit ? $amount : 0,
                'memo' => sprintf('%s — %s', $label, $company->name),
            ];

            $draft = $this->journalService->createDraft([
                'entry_date' => $entryDate,
                'currency' => $currency->value,
                'reference' => $entry->voucher_number,
                'description' => $description,
                'lines' => $isDeposit ? [$cashLine, $arLine] : [$arLine, $cashLine],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);

            $entry->update([
                'journal_entry_id' => $posted->id,
            ]);

            $this->attachFile($entry, $posted, $attachment);

            Log::info('Company wallet entry recorded.', [
                'company_id' => $company->id,
                'entry_id' => $entry->id,
                'type' => $type->value,
                'amount' => $amount,
                'currency' => $currency->value,
                'journal_entry_id' => $posted->id,
                'user_id' => $actor->id,
            ]);

            return $entry->load(['creator:id,name', 'journalEntry:id,voucher_number']);
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

            if ($locked->journal_entry_id) {
                $journal = JournalEntry::query()->find($locked->journal_entry_id);
                if ($journal && $journal->isPosted()) {
                    $this->journalService->void($journal, $actor, 'Wallet entry deleted');
                }
            }

            $locked->assignedChassis()->delete();
            $locked->delete();

            Log::info('Company wallet entry deleted.', [
                'company_id' => $company->id,
                'entry_id' => $locked->id,
                'voucher_number' => $locked->voucher_number,
                'type' => $locked->type->value,
                'amount' => (string) $locked->amount,
                'currency' => $currency->value,
                'journal_entry_id' => $locked->journal_entry_id,
                'user_id' => $actor->id,
            ]);
        });
    }

    public function replaceAttachment(
        Company $company,
        CompanyWalletEntry $entry,
        User $actor,
        UploadedFile $file
    ): CompanyWalletEntry {
        if ((int) $entry->company_id !== (int) $company->id) {
            abort(404);
        }

        return DB::transaction(function () use ($entry, $actor, $file): CompanyWalletEntry {
            $locked = CompanyWalletEntry::query()
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $journal = $locked->journal_entry_id
                ? JournalEntry::query()->find($locked->journal_entry_id)
                : null;

            $this->attachFile($locked, $journal, $file);

            Log::info('Company wallet attachment replaced.', [
                'company_id' => $locked->company_id,
                'entry_id' => $locked->id,
                'user_id' => $actor->id,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  list<array{id: int, land_trip_car_id: int|null, chassis_no: string}>  $chassis
     * @return array<string, mixed>
     */
    public function transform(CompanyWalletEntry $entry, array $chassis = []): array
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
            'entry_date' => $entry->entry_date?->toDateString(),
            'created_at' => ApplicationTimezone::formatDateTime($entry->created_at),
            'created_by_name' => $entry->creator?->name,
            'journal_voucher' => $entry->journalEntry?->voucher_number,
            ...AttachmentMeta::payload(
                $entry->attachmentUrl(),
                $entry->attachment_original_name,
                $entry->attachment_path,
                $entry->updated_at?->timestamp
            ),
            'amount_words_ar' => $words['arabic'],
            'amount_words_ckb' => $words['kurdish'],
            'chassis' => $chassis,
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

        $entry->loadMissing(['creator:id,name', 'journalEntry:id,voucher_number']);

        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'entry' => $this->transform($entry, $this->chassisService->forPayable($entry)),
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

    private function attachFile(CompanyWalletEntry $entry, ?JournalEntry $journal, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        $oldPath = $entry->attachment_path;
        $path = $file->store('land-payments/wallet/'.$entry->id, 'public');
        $name = mb_substr($file->getClientOriginalName(), 0, 180);

        $entry->update([
            'attachment_path' => $path,
            'attachment_original_name' => $name,
        ]);
        $journal?->update([
            'attachment_path' => $path,
        ]);

        if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
