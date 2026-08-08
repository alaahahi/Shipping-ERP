<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\IranCarStatus;
use App\Models\IranCar;
use App\Models\IranCarPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IranCarPaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly IranCarReceivableAccountService $iranReceivableAccounts,
        private readonly IranCarService $iranCarService
    ) {}

    /**
     * @param  array{
     *     payment_date: string,
     *     amount: float|int|string,
     *     debit_account_id: int,
     *     reference?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(IranCar $car, array $data, User $actor): IranCarPayment
    {
        return DB::transaction(function () use ($car, $data, $actor): IranCarPayment {
            $locked = IranCar::query()->lockForUpdate()->findOrFail($car->id);
            $locked->load(['company']);

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages([
                    'amount' => 'Cannot collect payment on a cancelled car.',
                ]);
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount must be greater than zero.',
                ]);
            }

            $remaining = $locked->remainingAmount();
            if ($amount - $remaining > 0.009) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('Payment exceeds remaining balance (%s).', number_format($remaining, 2, '.', '')),
                ]);
            }

            $debitAccount = $this->iranCarService->resolveCashBankAccount((int) $data['debit_account_id']);
            $receivable = $this->iranReceivableAccounts->resolveFor($locked->company);
            $memo = sprintf('Iran car payment — %s', $locked->vin);

            $payment = IranCarPayment::query()->create([
                'iran_car_id' => $locked->id,
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
                        'company_id' => $locked->company_id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => $memo,
                    ],
                    [
                        'account_id' => $receivable->id,
                        'company_id' => $locked->company_id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => $memo,
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);
            $payment->update(['journal_entry_id' => $posted->id]);

            $this->iranCarService->refreshStatus($locked->fresh());

            return $payment->fresh(['debitAccount', 'journalEntry', 'creator']);
        });
    }

    public function delete(IranCar $car, IranCarPayment $payment, User $actor): void
    {
        abort_unless((int) $payment->iran_car_id === (int) $car->id, 404);

        DB::transaction(function () use ($car, $payment, $actor): void {
            $payment->loadMissing('journalEntry');

            if ($payment->journalEntry?->isPosted()) {
                $this->journalService->void(
                    $payment->journalEntry,
                    $actor,
                    'Iran car payment reversed.'
                );
            }

            Log::info('Iran car payment deleted.', [
                'iran_car_id' => $car->id,
                'payment_id' => $payment->id,
                'voucher_number' => $payment->voucher_number,
                'amount' => $payment->amount,
                'deleted_by' => $actor->id,
            ]);

            $payment->delete();

            $fresh = $car->fresh();
            if ($fresh && $fresh->status !== IranCarStatus::Cancelled) {
                $this->iranCarService->refreshStatus($fresh);
            }
        });
    }

    public function nextVoucherNumber(): string
    {
        $prefix = 'ICP-'.now()->format('Ym').'-';
        $latest = IranCarPayment::query()
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
