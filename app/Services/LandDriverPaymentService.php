<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\LandDriverPaymentType;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\LandDriverPayment;
use App\Models\LandTrip;
use App\Models\User;
use App\Support\ApplicationTimezone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LandDriverPaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CompanyReceivableAccountService $companyReceivableAccounts,
        private readonly LandTripCashAccountService $cashAccounts
    ) {}

    /**
     * @return Collection<int, LandDriverPayment>
     */
    public function models(Company $company)
    {
        return LandDriverPayment::query()
            ->where('company_id', $company->id)
            ->with(['creator:id,name', 'cashAccount:id,code,name', 'journalEntry:id,voucher_number'])
            ->latest('payment_date')
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payload(Company $company): array
    {
        return $this->models($company)
            ->map(fn (LandDriverPayment $payment) => $this->transform($payment))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function driverNames(?Company $company = null): array
    {
        $query = LandTrip::query()
            ->whereNotNull('driver_name')
            ->where('driver_name', '!=', '');

        if ($company) {
            $query->where('company_id', $company->id);
        }

        $fromTrips = $query
            ->distinct()
            ->orderBy('driver_name')
            ->limit(200)
            ->pluck('driver_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();

        $fromPayments = LandDriverPayment::query()
            ->when($company, fn ($builder) => $builder->where('company_id', $company->id))
            ->whereNotNull('driver_name')
            ->where('driver_name', '!=', '')
            ->distinct()
            ->orderBy('driver_name')
            ->limit(200)
            ->pluck('driver_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();

        return $fromTrips
            ->merge($fromPayments)
            ->unique(fn (string $name) => mb_strtolower($name))
            ->sort(fn (string $a, string $b) => strcasecmp($a, $b))
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     driver_name: string,
     *     cmr_number?: string|null,
     *     cars_count: int|string,
     *     type: string,
     *     payment_date: string,
     *     amount: float|int|string
     * }  $data
     */
    public function create(Company $company, array $data, User $actor, ?UploadedFile $attachment = null): LandDriverPayment
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter an amount greater than zero.',
            ]);
        }

        $type = LandDriverPaymentType::from($data['type']);
        $cashAccount = $this->cashAccounts->resolve();
        $receivable = $this->companyReceivableAccounts->resolveFor($company);
        $driverName = trim((string) $data['driver_name']);
        $carsCount = (int) $data['cars_count'];
        $cmr = $this->nullableString($data['cmr_number'] ?? null);
        $memo = sprintf('Driver payment — %s · %s', $driverName, $company->name);
        $description = sprintf(
            'Driver payment — %s · %s · %s USD',
            $driverName,
            $company->name,
            number_format($amount, 2, '.', '')
        );

        $payment = DB::transaction(function () use (
            $company,
            $actor,
            $amount,
            $type,
            $cashAccount,
            $receivable,
            $driverName,
            $carsCount,
            $cmr,
            $data,
            $memo,
            $description,
            $attachment
        ): LandDriverPayment {
            $payment = LandDriverPayment::query()->create([
                'company_id' => $company->id,
                'driver_name' => $driverName,
                'cmr_number' => $cmr,
                'cars_count' => $carsCount,
                'type' => $type,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'currency' => Currency::USD,
                'cash_account_id' => $cashAccount->id,
                'created_by' => $actor->id,
            ]);

            $draft = $this->journalService->createDraft([
                'entry_date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
                'currency' => Currency::USD->value,
                'reference' => sprintf('LDP-%s', $payment->id),
                'description' => $description,
                'lines' => [
                    [
                        'account_id' => $receivable->id,
                        'company_id' => $company->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => $memo,
                    ],
                    [
                        'account_id' => $cashAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => sprintf('%s · %s', $cashAccount->code, $cashAccount->name),
                    ],
                ],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);

            $payment->update([
                'journal_entry_id' => $posted->id,
            ]);

            $this->attachFile($payment, $posted, $attachment);

            return $payment->fresh(['journalEntry', 'cashAccount', 'creator', 'company']);
        });

        Log::info('Land driver payment posted.', [
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'journal_entry_id' => $payment->journal_entry_id,
            'amount' => $amount,
            'user_id' => $actor->id,
        ]);

        return $payment;
    }

    /**
     * @param  array{
     *     driver_name: string,
     *     cmr_number?: string|null,
     *     cars_count: int|string,
     *     type: string
     * }  $data
     */
    public function update(Company $company, LandDriverPayment $payment, array $data, User $actor): LandDriverPayment
    {
        if ((int) $payment->company_id !== (int) $company->id) {
            abort(404);
        }

        $updated = DB::transaction(function () use ($company, $payment, $data, $actor): LandDriverPayment {
            $locked = LandDriverPayment::query()
                ->whereKey($payment->id)
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->firstOrFail();

            $driverName = trim((string) $data['driver_name']);
            $carsCount = (int) $data['cars_count'];
            $cmr = $this->nullableString($data['cmr_number'] ?? null);
            $type = LandDriverPaymentType::from($data['type']);

            $locked->update([
                'driver_name' => $driverName,
                'cmr_number' => $cmr,
                'cars_count' => $carsCount,
                'type' => $type,
            ]);

            $this->syncJournalDescription($company, $locked);

            Log::info('Land driver payment updated.', [
                'company_id' => $company->id,
                'payment_id' => $locked->id,
                'user_id' => $actor->id,
            ]);

            return $locked->fresh(['journalEntry', 'cashAccount', 'creator', 'company']) ?? $locked;
        });

        return $updated;
    }

    public function delete(Company $company, LandDriverPayment $payment, User $actor): void
    {
        if ((int) $payment->company_id !== (int) $company->id) {
            abort(404);
        }

        DB::transaction(function () use ($company, $payment, $actor): void {
            $locked = LandDriverPayment::query()
                ->whereKey($payment->id)
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->voidLinkedJournal($locked->journal_entry_id, $actor, 'Driver payment deleted');

            $this->forgetChassis($locked);
            $locked->delete();

            Log::info('Land driver payment deleted.', [
                'company_id' => $company->id,
                'payment_id' => $locked->id,
                'journal_entry_id' => $locked->journal_entry_id,
                'driver_name' => $locked->driver_name,
                'amount' => (string) $locked->amount,
                'user_id' => $actor->id,
            ]);
        });
    }

    public function replaceAttachment(
        Company $company,
        LandDriverPayment $payment,
        User $actor,
        UploadedFile $file
    ): LandDriverPayment {
        if ((int) $payment->company_id !== (int) $company->id) {
            abort(404);
        }

        return DB::transaction(function () use ($payment, $actor, $file): LandDriverPayment {
            $locked = LandDriverPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $journal = $locked->journal_entry_id
                ? JournalEntry::query()->find($locked->journal_entry_id)
                : null;

            $this->attachFile($locked, $journal, $file);

            Log::info('Land driver payment attachment replaced.', [
                'company_id' => $locked->company_id,
                'payment_id' => $locked->id,
                'user_id' => $actor->id,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  list<array{id: int, land_trip_car_id: int|null, chassis_no: string}>  $chassis
     * @return array<string, mixed>
     */
    public function transform(LandDriverPayment $payment, array $chassis = []): array
    {
        $type = $payment->type instanceof LandDriverPaymentType
            ? $payment->type->value
            : (string) $payment->type;

        $currency = $payment->currency instanceof Currency
            ? $payment->currency->value
            : (string) $payment->currency;

        return [
            'id' => $payment->id,
            'driver_name' => $payment->driver_name,
            'cmr_number' => $payment->cmr_number,
            'cars_count' => $payment->cars_count,
            'type' => $type,
            'payment_date' => $payment->payment_date?->toDateString(),
            'amount' => (string) $payment->amount,
            'currency' => $currency,
            'cash_account_name' => $payment->cashAccount?->name,
            'journal_voucher' => $payment->journalEntry?->voucher_number,
            'has_attachment' => filled($payment->attachment_path),
            'attachment_url' => $this->versionedAttachmentUrl($payment->attachmentUrl(), $payment->updated_at?->timestamp),
            'attachment_name' => $payment->attachment_original_name,
            'attachment_is_image' => $this->attachmentIsImage($payment->attachment_original_name, $payment->attachment_path),
            'attachment_is_pdf' => $this->attachmentIsPdf($payment->attachment_original_name, $payment->attachment_path),
            'created_at' => ApplicationTimezone::formatDateTime($payment->created_at),
            'created_by_name' => $payment->creator?->name,
            'chassis' => $chassis,
        ];
    }

    private function syncJournalDescription(Company $company, LandDriverPayment $payment): void
    {
        if (! $payment->journal_entry_id) {
            return;
        }

        $journal = JournalEntry::query()->find($payment->journal_entry_id);
        if (! $journal || ! $journal->isPosted()) {
            return;
        }

        $amount = number_format((float) $payment->amount, 2, '.', '');
        $description = sprintf(
            'Driver payment — %s · %s · %s USD',
            $payment->driver_name,
            $company->name,
            $amount
        );
        $memo = sprintf('Driver payment — %s · %s', $payment->driver_name, $company->name);

        $this->journalService->updatePostedMeta($journal, [
            'description' => $description,
        ]);

        $journal->lines()
            ->where('debit', '>', 0)
            ->update(['memo' => $memo]);
    }

    private function forgetChassis(LandDriverPayment $payment): void
    {
        $payment->assignedChassis()->delete();
    }

    private function voidLinkedJournal(?int $journalEntryId, User $actor, string $reason): void
    {
        if (! $journalEntryId) {
            return;
        }

        $journal = JournalEntry::query()->find($journalEntryId);
        if (! $journal || ! $journal->isPosted()) {
            return;
        }

        $this->journalService->void($journal, $actor, $reason);
    }

    private function attachFile(LandDriverPayment $payment, ?JournalEntry $journal, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        $oldPath = $payment->attachment_path;
        $path = $file->store('land-payments/drivers/'.$payment->id, 'public');
        $name = mb_substr($file->getClientOriginalName(), 0, 180);

        $payment->update([
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

    private function versionedAttachmentUrl(?string $url, ?int $version): ?string
    {
        if (! $url) {
            return null;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.($version ?: time());
    }

    private function attachmentIsImage(?string $name, ?string $path): bool
    {
        return in_array($this->attachmentExtension($name, $path), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function attachmentIsPdf(?string $name, ?string $path): bool
    {
        return $this->attachmentExtension($name, $path) === 'pdf';
    }

    private function attachmentExtension(?string $name, ?string $path): string
    {
        return strtolower(pathinfo((string) ($name ?: $path ?: ''), PATHINFO_EXTENSION));
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
