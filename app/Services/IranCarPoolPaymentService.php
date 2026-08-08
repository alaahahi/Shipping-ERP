<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Company;
use App\Models\IranCarPoolPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IranCarPoolPaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly IranCarReceivableAccountService $iranReceivableAccounts,
        private readonly IranCarService $iranCarService
    ) {}

    /**
     * @param  array{
     *     company_id: int,
     *     payment_date: string,
     *     amount: float|int|string,
     *     debit_account_id: int,
     *     reference?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data, User $actor): IranCarPoolPayment
    {
        return DB::transaction(function () use ($data, $actor): IranCarPoolPayment {
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount must be greater than zero.',
                ]);
            }

            $remaining = $this->iranCarService->globalPaymentSummary()['remaining'];
            if ($amount - $remaining > 0.009) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('Payment exceeds remaining balance (%s).', number_format($remaining, 2, '.', '')),
                ]);
            }

            if ($remaining <= 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'There is no remaining Iran cars balance to collect.',
                ]);
            }

            $company = Company::query()->findOrFail((int) $data['company_id']);
            $debitAccount = $this->iranCarService->resolveCashBankAccount((int) $data['debit_account_id']);
            $receivable = $this->iranReceivableAccounts->resolveFor($company);
            $memo = sprintf('Iran cars pool payment — %s', $company->name);

            $payment = IranCarPoolPayment::query()->create([
                'company_id' => $company->id,
                'voucher_number' => $this->nextVoucherNumber(),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'currency' => Currency::USD->value,
                'debit_account_id' => $debitAccount->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $draft = $this->journalService->createDraft([
                'entry_date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
                'currency' => Currency::USD->value,
                'reference' => $payment->voucher_number,
                'description' => $memo,
                'lines' => [
                    [
                        'account_id' => $debitAccount->id,
                        'company_id' => $company->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => $memo,
                    ],
                    [
                        'account_id' => $receivable->id,
                        'company_id' => $company->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => $memo,
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);
            $payment->update(['journal_entry_id' => $posted->id]);

            $this->iranCarService->refreshAllSoldStatuses();

            return $payment->fresh(['company:id,name', 'debitAccount', 'journalEntry', 'creator']);
        });
    }

    public function delete(IranCarPoolPayment $payment, User $actor): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            $payment->loadMissing('journalEntry');

            if ($payment->journalEntry?->isPosted()) {
                $this->journalService->void(
                    $payment->journalEntry,
                    $actor,
                    'Iran cars pool payment reversed.'
                );
            }

            Log::info('Iran cars pool payment deleted.', [
                'pool_payment_id' => $payment->id,
                'company_id' => $payment->company_id,
                'voucher_number' => $payment->voucher_number,
                'amount' => $payment->amount,
                'deleted_by' => $actor->id,
            ]);

            $payment->delete();

            $this->iranCarService->refreshAllSoldStatuses();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTransformed(): array
    {
        return IranCarPoolPayment::query()
            ->with([
                'company:id,name',
                'debitAccount:id,code,name',
                'journalEntry:id,voucher_number,status',
                'creator:id,name',
            ])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (IranCarPoolPayment $payment) => $this->transform($payment))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(IranCarPoolPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'company_id' => $payment->company_id,
            'company_name' => $payment->company?->name,
            'voucher_number' => $payment->voucher_number,
            'payment_date' => $payment->payment_date?->toDateString(),
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency?->value ?? Currency::USD->value,
            'debit_account_id' => $payment->debit_account_id,
            'debit_account_label' => $payment->debitAccount
                ? $payment->debitAccount->code.' — '.$payment->debitAccount->name
                : null,
            'journal_voucher' => $payment->journalEntry?->voucher_number,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'created_by_name' => $payment->creator?->name,
        ];
    }

    public function nextVoucherNumber(): string
    {
        $prefix = 'ICPP-'.now()->format('Ym').'-';
        $latest = IranCarPoolPayment::query()
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
}
