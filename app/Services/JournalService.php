<?php

namespace App\Services;

use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalService
{
    /**
     * @param  array{search?: string|null, status?: string|null, currency?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = JournalEntry::query()
            ->with(['creator:id,name', 'lines'])
            ->latest('entry_date')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
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
     *     entry_date: string,
     *     currency: string,
     *     reference?: string|null,
     *     description: string,
     *     lines: list<array{account_id: int, debit: float|int|string, credit: float|int|string, memo?: string|null, company_id?: int|null, voyage_id?: int|null, owner_id?: int|null}>
     * }  $data
     */
    public function createDraft(array $data, User $actor): JournalEntry
    {
        return DB::transaction(function () use ($data, $actor): JournalEntry {
            $this->assertBalancedLines($data['lines']);
            $this->assertAccountsMatchCurrency($data['lines'], $data['currency']);

            $entry = JournalEntry::query()->create([
                'voucher_number' => $this->nextVoucherNumber(),
                'entry_date' => $data['entry_date'],
                'currency' => $data['currency'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'status' => JournalStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->syncLines($entry, $data['lines']);

            return $entry->load(['lines.account', 'creator:id,name']);
        });
    }

    /**
     * @param  array{
     *     entry_date: string,
     *     currency: string,
     *     reference?: string|null,
     *     description: string,
     *     lines: list<array{account_id: int, debit: float|int|string, credit: float|int|string, memo?: string|null, company_id?: int|null, voyage_id?: int|null, owner_id?: int|null}>
     * }  $data
     */
    public function updateDraft(JournalEntry $entry, array $data): JournalEntry
    {
        $this->assertDraft($entry);

        return DB::transaction(function () use ($entry, $data): JournalEntry {
            $this->assertBalancedLines($data['lines']);
            $this->assertAccountsMatchCurrency($data['lines'], $data['currency']);

            $entry->update([
                'entry_date' => $data['entry_date'],
                'currency' => $data['currency'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
            ]);

            $this->syncLines($entry, $data['lines']);

            return $entry->fresh(['lines.account', 'creator:id,name']);
        });
    }

    public function post(JournalEntry $entry, User $actor): JournalEntry
    {
        $this->assertDraft($entry);
        $entry->load('lines');

        if ($entry->lines->count() < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry requires at least two lines.',
            ]);
        }

        $this->assertBalancedLines($entry->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'debit' => $line->debit,
            'credit' => $line->credit,
        ])->all());

        $entry->update([
            'status' => JournalStatus::Posted,
            'posted_by' => $actor->id,
            'posted_at' => now(),
        ]);

        return $entry->fresh(['lines.account', 'creator:id,name', 'poster:id,name']);
    }

    public function void(JournalEntry $entry, User $actor, ?string $reason = null): JournalEntry
    {
        if (! $entry->isPosted()) {
            throw ValidationException::withMessages([
                'status' => 'Only posted entries can be voided.',
            ]);
        }

        $entry->update([
            'status' => JournalStatus::Void,
            'voided_by' => $actor->id,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);

        return $entry->fresh(['lines.account', 'creator:id,name', 'voider:id,name']);
    }

    public function nextVoucherNumber(): string
    {
        $prefix = 'JV-'.now()->format('Ym').'-';
        $latest = JournalEntry::query()
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
     * @param  list<array{account_id: int, debit: float|int|string, credit: float|int|string, memo?: string|null, company_id?: int|null, voyage_id?: int|null}>  $lines
     */
    private function syncLines(JournalEntry $entry, array $lines): void
    {
        $entry->lines()->delete();

        foreach ($lines as $line) {
            $debit = round((float) $line['debit'], 2);
            $credit = round((float) $line['credit'], 2);

            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Each journal line must have a debit or a credit amount.',
                ]);
            }

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    'lines' => 'A journal line cannot have both debit and credit amounts.',
                ]);
            }

            $entry->lines()->create([
                'account_id' => $line['account_id'],
                'company_id' => $line['company_id'] ?? null,
                'voyage_id' => $line['voyage_id'] ?? null,
                'owner_id' => $line['owner_id'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<array{account_id: int, debit?: mixed, credit?: mixed}>  $lines
     */
    private function assertBalancedLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry requires at least two lines.',
            ]);
        }

        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit += round((float) ($line['debit'] ?? 0), 2);
            $credit += round((float) ($line['credit'] ?? 0), 2);
        }

        if (round($debit, 2) !== round($credit, 2)) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry is not balanced. Total debit must equal total credit.',
            ]);
        }

        if ($debit <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry amount must be greater than zero.',
            ]);
        }
    }

    /**
     * @param  list<array{account_id: int}>  $lines
     */
    private function assertAccountsMatchCurrency(array $lines, string $currency): void
    {
        $accountIds = collect($lines)->pluck('account_id')->unique()->values();
        $accounts = Account::query()->whereIn('id', $accountIds)->get(['id', 'currency', 'is_active', 'code', 'name']);

        if ($accounts->count() !== $accountIds->count()) {
            throw ValidationException::withMessages([
                'lines' => 'One or more accounts were not found.',
            ]);
        }

        foreach ($accounts as $account) {
            if (! $account->is_active) {
                throw ValidationException::withMessages([
                    'lines' => "Account {$account->code} is inactive.",
                ]);
            }

            if ($account->currency->value !== $currency) {
                throw ValidationException::withMessages([
                    'lines' => "Account {$account->code} ({$account->name}) uses {$account->currency->value}, but this voucher is {$currency}. Dubai and Ship accounts typically use AED.",
                ]);
            }
        }
    }

    private function assertDraft(JournalEntry $entry): void
    {
        if (! $entry->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft journal entries can be edited.',
            ]);
        }
    }
}
