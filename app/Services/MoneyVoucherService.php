<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\MoneyVoucherStatus;
use App\Enums\MoneyVoucherType;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\MoneyVoucher;
use App\Models\User;
use App\Models\Voyage;
use App\Notifications\AccountingPostedNotification;
use App\Services\Concerns\ResolvesExpensePaymentAccounts;
use App\Support\ApplicationTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoneyVoucherService
{
    use ResolvesExpensePaymentAccounts;

    public function __construct(
        private readonly JournalService $journalService,
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly CompanyWhatsappNotificationService $whatsappNotificationService,
        private readonly CompanyReceivableAccountService $companyReceivableAccounts
    ) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     type?: string|null,
     *     status?: string|null,
     *     currency?: string|null
     * }  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = MoneyVoucher::query()
            ->with([
                'paymentAccount:id,code,name',
                'company:id,name',
                'voyage:id,voyage_number',
                'journalEntry:id,voucher_number,status',
                'creator:id,name',
            ])
            ->latest('voucher_date')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('counterparty', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('voyage', fn ($voyage) => $voyage->where('voyage_number', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     type: string,
     *     voucher_date: string,
     *     currency: string,
     *     amount: float|int|string,
     *     payment_account_id: int,
     *     company_id?: int|null,
     *     voyage_id?: int|null,
     *     counterparty?: string|null,
     *     reference?: string|null,
     *     description?: string|null,
     *     allocations?: list<array{voyage_id: int, amount: float|int|string}>
     * }  $data
     */
    public function create(array $data, User $actor): MoneyVoucher
    {
        $type = MoneyVoucherType::from($data['type']);
        $currency = Currency::from($data['currency']);
        $amount = round((float) $data['amount'], 2);
        $allocations = $this->normalizeAllocations($data['allocations'] ?? [], $amount, $type);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $this->resolvePaymentAccount((int) $data['payment_account_id'], $currency);
        $companyId = $this->resolveCompanyId($type, $data['company_id'] ?? null, $data['counterparty'] ?? null);

        if (! empty($data['voyage_id'])) {
            Voyage::query()->whereKey($data['voyage_id'])->firstOrFail();
        }

        return DB::transaction(function () use ($data, $actor, $type, $currency, $amount, $allocations, $companyId): MoneyVoucher {
            $voucher = MoneyVoucher::query()->create([
                'voucher_number' => $this->nextVoucherNumber($type),
                'type' => $type,
                'voucher_date' => $data['voucher_date'],
                'currency' => $currency,
                'amount' => $amount,
                'payment_account_id' => $data['payment_account_id'],
                'company_id' => $companyId,
                'voyage_id' => $data['voyage_id'] ?? null,
                'counterparty' => $data['counterparty'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => MoneyVoucherStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->syncAllocations($voucher, $allocations);

            return $voucher->load(['paymentAccount', 'company', 'voyage', 'allocations.voyage', 'creator']);
        });
    }

    /**
     * @param  array{
     *     voucher_date: string,
     *     currency: string,
     *     amount: float|int|string,
     *     payment_account_id: int,
     *     company_id?: int|null,
     *     voyage_id?: int|null,
     *     counterparty?: string|null,
     *     reference?: string|null,
     *     description?: string|null,
     *     allocations?: list<array{voyage_id: int, amount: float|int|string}>
     * }  $data
     */
    public function update(MoneyVoucher $voucher, array $data): MoneyVoucher
    {
        $this->assertDraft($voucher);

        $currency = Currency::from($data['currency']);
        $amount = round((float) $data['amount'], 2);
        $allocations = $this->normalizeAllocations($data['allocations'] ?? [], $amount, $voucher->type);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $this->resolvePaymentAccount((int) $data['payment_account_id'], $currency);
        $companyId = $this->resolveCompanyId(
            $voucher->type,
            $data['company_id'] ?? null,
            $data['counterparty'] ?? null
        );

        if (! empty($data['voyage_id'])) {
            Voyage::query()->whereKey($data['voyage_id'])->firstOrFail();
        }

        return DB::transaction(function () use ($voucher, $data, $currency, $amount, $allocations, $companyId): MoneyVoucher {
            $voucher->update([
                'voucher_date' => $data['voucher_date'],
                'currency' => $currency,
                'amount' => $amount,
                'payment_account_id' => $data['payment_account_id'],
                'company_id' => $companyId,
                'voyage_id' => $data['voyage_id'] ?? null,
                'counterparty' => $data['counterparty'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            $this->syncAllocations($voucher, $allocations);

            return $voucher->fresh(['paymentAccount', 'company', 'voyage', 'allocations.voyage', 'creator']);
        });
    }

    public function delete(MoneyVoucher $voucher): void
    {
        $this->assertDraft($voucher);
        $voucher->delete();
    }

    public function post(MoneyVoucher $voucher, User $actor): MoneyVoucher
    {
        $voucher->loadMissing(['paymentAccount', 'company', 'voyage', 'allocations.voyage', 'journalEntry']);
        $this->assertDraft($voucher);

        $currency = $voucher->currency;
        if (! in_array($currency, [Currency::USD, Currency::AED], true)) {
            throw ValidationException::withMessages([
                'currency' => 'Only USD and AED vouchers can be posted.',
            ]);
        }

        $amount = round((float) $voucher->amount, 2);
        $paymentAccount = $this->resolvePaymentAccount($voucher->payment_account_id, $currency);

        if ($currency !== Currency::USD) {
            throw ValidationException::withMessages([
                'currency' => 'AR/AP clearing currently supports USD only (accounts 1600 / 2100).',
            ]);
        }

        if ($voucher->type === MoneyVoucherType::Receipt && ! $voucher->company) {
            throw ValidationException::withMessages([
                'company_id' => 'Receipt vouchers must be linked to a shipping company.',
            ]);
        }

        $clearingAccount = $voucher->type === MoneyVoucherType::Receipt
            ? $this->companyReceivableAccounts->resolveFor($voucher->company)
            : $this->resolveExpenseAccountByCode('2100', Currency::USD);

        $partyName = $voucher->company?->name
            ?: ($voucher->counterparty ?: 'Counterparty');

        $description = $voucher->description
            ?: sprintf(
                '%s — %s%s',
                $voucher->type->label(),
                $partyName,
                $voucher->voyage ? ' · '.$voucher->voyage->voyage_number : ''
            );

        $voucher = DB::transaction(function () use (
            $voucher,
            $actor,
            $currency,
            $amount,
            $paymentAccount,
            $clearingAccount,
            $description
        ): MoneyVoucher {
            if ($voucher->type === MoneyVoucherType::Receipt) {
                $lines = [
                    [
                        'account_id' => $paymentAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => 'Cash/bank received',
                    ],
                ];

                $allocated = 0.0;
                foreach ($voucher->allocations as $allocation) {
                    $allocAmount = round((float) $allocation->amount, 2);
                    if ($allocAmount <= 0) {
                        continue;
                    }

                    $allocated = round($allocated + $allocAmount, 2);
                    $lines[] = [
                        'account_id' => $clearingAccount->id,
                        'company_id' => $voucher->company_id,
                        'voyage_id' => $allocation->voyage_id,
                        'debit' => 0,
                        'credit' => $allocAmount,
                        'memo' => sprintf(
                            'Receipt — %s · voyage %s',
                            $voucher->company?->name ?? 'Company',
                            $allocation->voyage?->voyage_number ?? $allocation->voyage_id
                        ),
                    ];
                }

                $remainder = round($amount - $allocated, 2);
                if ($remainder < 0) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Allocations cannot exceed the voucher amount.',
                    ]);
                }

                if ($remainder > 0) {
                    $lines[] = [
                        'account_id' => $clearingAccount->id,
                        'company_id' => $voucher->company_id,
                        'voyage_id' => $voucher->voyage_id,
                        'debit' => 0,
                        'credit' => $remainder,
                        'memo' => sprintf(
                            'Receipt — %s%s',
                            $voucher->company?->name ?? 'Company',
                            $voucher->voyage_id ? '' : ' (unallocated)'
                        ),
                    ];
                }
            } else {
                $lines = [
                    [
                        'account_id' => $clearingAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => 'Clear accounts payable',
                    ],
                    [
                        'account_id' => $paymentAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => 'Cash/bank paid',
                    ],
                ];
            }

            $draft = $this->journalService->createDraft([
                'entry_date' => $voucher->voucher_date?->toDateString() ?? now()->toDateString(),
                'currency' => $currency->value,
                'reference' => $voucher->voucher_number,
                'description' => $description,
                'lines' => $lines,
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);

            $voucher->update([
                'status' => MoneyVoucherStatus::Posted,
                'journal_entry_id' => $posted->id,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            return $voucher->fresh([
                'paymentAccount',
                'company',
                'voyage',
                'allocations.voyage',
                'journalEntry',
                'creator',
                'poster',
            ]);
        });

        $this->notificationDispatchService->notifyByPermissions(
            Permission::AccountingView->value,
            new AccountingPostedNotification(
                $voucher->type->label().' posted',
                sprintf(
                    '%s — %s (%s %s).',
                    $voucher->voucher_number,
                    $voucher->journalEntry?->voucher_number ?? '—',
                    number_format($amount, 2, '.', ''),
                    $currency->value
                ),
                route('journals.show', $voucher->journal_entry_id),
                $voucher->journalEntry?->voucher_number
            ),
            $actor->id
        );

        if ($voucher->type === MoneyVoucherType::Receipt) {
            $this->whatsappNotificationService->notifyPaymentReceived($voucher);
        }

        return $voucher;
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(MoneyVoucher $voucher): array
    {
        $voucher->loadMissing([
            'paymentAccount',
            'company',
            'voyage',
            'allocations.voyage',
            'journalEntry',
            'creator',
            'poster',
        ]);

        return [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'type' => $voucher->type->value,
            'type_label' => $voucher->type->label(),
            'type_tone' => $voucher->type->tone(),
            'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
            'currency' => $voucher->currency->value,
            'amount' => number_format((float) $voucher->amount, 2, '.', ''),
            'payment_account_id' => $voucher->payment_account_id,
            'payment_account' => $voucher->paymentAccount
                ? $voucher->paymentAccount->code.' — '.$voucher->paymentAccount->name
                : null,
            'company_id' => $voucher->company_id,
            'company_name' => $voucher->company?->name,
            'voyage_id' => $voucher->voyage_id,
            'voyage_number' => $voucher->voyage?->voyage_number,
            'counterparty' => $voucher->counterparty,
            'reference' => $voucher->reference,
            'description' => $voucher->description,
            'allocations' => $voucher->allocations->map(fn ($row) => [
                'voyage_id' => $row->voyage_id,
                'voyage_number' => $row->voyage?->voyage_number,
                'amount' => number_format((float) $row->amount, 2, '.', ''),
            ])->values()->all(),
            'status' => $voucher->status->value,
            'status_label' => $voucher->status->label(),
            'status_tone' => $voucher->status->tone(),
            'is_draft' => $voucher->isDraft(),
            'is_posted' => $voucher->isPosted(),
            'journal_entry_id' => $voucher->journal_entry_id,
            'journal_voucher' => $voucher->journalEntry?->voucher_number,
            'created_by_name' => $voucher->creator?->name,
            'posted_by_name' => $voucher->poster?->name,
            'posted_at' => ApplicationTimezone::formatDateTime($voucher->posted_at),
        ];
    }

    public function nextVoucherNumber(MoneyVoucherType $type): string
    {
        $prefix = $type->prefix().'-'.now()->format('Ym').'-';
        $latest = MoneyVoucher::query()
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

    /**
     * @param  list<array{voyage_id?: mixed, amount?: mixed}>  $rows
     * @return list<array{voyage_id: int, amount: float}>
     */
    private function normalizeAllocations(array $rows, float $voucherAmount, MoneyVoucherType $type): array
    {
        if ($type !== MoneyVoucherType::Receipt) {
            return [];
        }

        $normalized = [];
        $total = 0.0;

        foreach ($rows as $index => $row) {
            $voyageId = (int) ($row['voyage_id'] ?? 0);
            $amount = round((float) ($row['amount'] ?? 0), 2);

            if ($voyageId <= 0 || $amount <= 0) {
                continue;
            }

            if (! Voyage::query()->whereKey($voyageId)->exists()) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.voyage_id" => 'Voyage not found.',
                ]);
            }

            if (isset($normalized[$voyageId])) {
                throw ValidationException::withMessages([
                    'allocations' => 'Each voyage can appear only once in allocations.',
                ]);
            }

            $normalized[$voyageId] = [
                'voyage_id' => $voyageId,
                'amount' => $amount,
            ];
            $total = round($total + $amount, 2);
        }

        if ($total > $voucherAmount) {
            throw ValidationException::withMessages([
                'allocations' => 'Allocated total cannot exceed the receipt amount.',
            ]);
        }

        return array_values($normalized);
    }

    /**
     * @param  list<array{voyage_id: int, amount: float}>  $allocations
     */
    private function syncAllocations(MoneyVoucher $voucher, array $allocations): void
    {
        $voucher->allocations()->delete();

        foreach ($allocations as $row) {
            $voucher->allocations()->create($row);
        }
    }

    private function resolveCompanyId(MoneyVoucherType $type, mixed $companyId, ?string $counterparty): ?int
    {
        if ($type === MoneyVoucherType::Receipt) {
            $id = (int) $companyId;
            if ($id <= 0) {
                throw ValidationException::withMessages([
                    'company_id' => 'Select a shipping company for this receipt.',
                ]);
            }

            $company = Company::query()->whereKey($id)->where('is_active', true)->first();
            if (! $company) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company not found.',
                ]);
            }

            return $company->id;
        }

        if (! empty($companyId)) {
            $company = Company::query()->whereKey((int) $companyId)->first();
            if (! $company) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company not found.',
                ]);
            }

            return $company->id;
        }

        return null;
    }

    private function assertDraft(MoneyVoucher $voucher): void
    {
        if (! $voucher->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft money vouchers can be changed.',
            ]);
        }
    }
}
