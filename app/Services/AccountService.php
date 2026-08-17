<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function __construct(
        private readonly CompanyReceivableAccountService $companyReceivableAccounts,
        private readonly JournalService $journalService
    ) {}

    /**
     * @param  array{search?: string|null, type?: string|null, currency?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20, ?int $page = null): LengthAwarePaginator
    {
        $query = Account::query()
            ->with('parent:id,code,name')
            ->orderBy('code');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        $paginator = $query->paginate(
            $perPage,
            ['*'],
            'page',
            $page ?? max(1, (int) request()->integer('page', 1)),
        );

        return $paginator->appends(collect($filters)->filter()->all());
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     type: string,
     *     currency: string,
     *     parent_id?: int|null,
     *     description?: string|null,
     *     is_active?: bool,
     *     show_on_dashboard?: bool
     * }  $data
     */
    public function create(array $data): Account
    {
        return DB::transaction(function () use ($data): Account {
            $this->assertParentCompatible($data);

            return Account::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'],
                'currency' => $data['currency'],
                'parent_id' => $data['parent_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'show_on_dashboard' => $data['show_on_dashboard'] ?? false,
                'is_system' => false,
            ]);
        });
    }

    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     type: string,
     *     currency: string,
     *     parent_id?: int|null,
     *     description?: string|null,
     *     is_active?: bool,
     *     show_on_dashboard?: bool
     * }  $data
     */
    public function update(Account $account, array $data): Account
    {
        return DB::transaction(function () use ($account, $data): Account {
            $isCompanyAr = $this->companyReceivableAccounts->isCompanyReceivable($account);

            // Company AR subsidiaries stay structurally tied to the control account.
            if ($isCompanyAr) {
                $data['code'] = $account->code;
                $data['type'] = $account->type->value;
                $data['currency'] = $account->currency->value;
                $data['parent_id'] = $account->parent_id;
            } elseif ($this->hasPostedMovements($account)) {
                // Preserve double-entry integrity once the account has posted history.
                if (($data['type'] ?? null) !== $account->type->value) {
                    throw ValidationException::withMessages([
                        'type' => 'Account type cannot be changed after posted journal movements.',
                    ]);
                }

                if (($data['currency'] ?? null) !== $account->currency->value) {
                    throw ValidationException::withMessages([
                        'currency' => 'Account currency cannot be changed after posted journal movements.',
                    ]);
                }
            }

            $this->assertParentCompatible($data, $account->id);

            $account->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'],
                'currency' => $data['currency'],
                'parent_id' => $data['parent_id'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? $account->is_active,
                'show_on_dashboard' => $data['show_on_dashboard'] ?? $account->show_on_dashboard,
            ]);

            return $account->fresh('parent:id,code,name');
        });
    }

    public function delete(Account $account, ?User $actor = null): void
    {
        if ($this->hasPostedMovements($account)) {
            throw ValidationException::withMessages([
                'account' => 'This account cannot be deleted because it has posted journal movements.',
            ]);
        }

        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Accounts with child accounts cannot be deleted.',
            ]);
        }

        if ($this->companyReceivableAccounts->isCompanyReceivable($account)) {
            throw ValidationException::withMessages([
                'account' => 'Company receivable accounts cannot be deleted while linked to a company.',
            ]);
        }

        DB::transaction(function () use ($account, $actor): void {
            Log::info('Account soft-deleted', [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'is_system' => (bool) $account->is_system,
                'deleted_by' => $actor?->id,
            ]);

            $account->delete();
        });
    }

    public function hasPostedMovements(Account $account): bool
    {
        return $this->postedLinesQuery($account)->exists();
    }

    public function isCompanyReceivable(Account $account): bool
    {
        return $this->companyReceivableAccounts->isCompanyReceivable($account);
    }

    /**
     * @return list<array{id: int, code: string, name: string, label: string}>
     */
    public function counterpartOptions(Account $account): array
    {
        return Account::query()
            ->where('is_active', true)
            ->whereKeyNot($account->id)
            ->where('currency', $account->currency)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'label' => "{$item->code} — {$item->name}",
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, name: string, currency: string, balance: string}>
     */
    public function dashboardShortcuts(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where('show_on_dashboard', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'currency' => $account->currency->value,
                'balance' => $this->balance($account),
            ])
            ->all();
    }

    public function toggleDashboard(Account $account): Account
    {
        $account->update([
            'show_on_dashboard' => ! $account->show_on_dashboard,
        ]);

        return $account->fresh();
    }

    /**
     * Receipt: Dr this account / Cr counterpart.
     * Payment: Dr counterpart / Cr this account.
     *
     * @param  array{type: string, counterpart_account_id: int, amount: float|int|string, entry_date: string, description?: string|null}  $data
     */
    public function postMovement(Account $account, array $data, User $actor, ?UploadedFile $attachment = null): JournalEntry
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $counterpart = Account::query()
            ->whereKey($data['counterpart_account_id'])
            ->where('is_active', true)
            ->first();

        if (! $counterpart) {
            throw ValidationException::withMessages([
                'counterpart_account_id' => 'Select a valid account.',
            ]);
        }

        if ($counterpart->id === $account->id) {
            throw ValidationException::withMessages([
                'counterpart_account_id' => 'Choose a different account.',
            ]);
        }

        if ($counterpart->currency !== $account->currency) {
            throw ValidationException::withMessages([
                'counterpart_account_id' => 'Both accounts must use the same currency.',
            ]);
        }

        $isReceipt = ($data['type'] ?? '') === 'receipt';
        $label = $isReceipt ? 'Receipt' : 'Payment';
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            $description = sprintf('%s · %s ↔ %s', $label, $account->code, $counterpart->code);
        }

        $thisLine = [
            'account_id' => $account->id,
            'debit' => $isReceipt ? $amount : 0,
            'credit' => $isReceipt ? 0 : $amount,
            'memo' => $label,
        ];
        $otherLine = [
            'account_id' => $counterpart->id,
            'debit' => $isReceipt ? 0 : $amount,
            'credit' => $isReceipt ? $amount : 0,
            'memo' => $label,
        ];

        return DB::transaction(function () use ($account, $data, $actor, $description, $thisLine, $otherLine, $attachment): JournalEntry {
            $draft = $this->journalService->createDraft([
                'entry_date' => $data['entry_date'],
                'currency' => $account->currency->value,
                'reference' => null,
                'description' => $description,
                'lines' => [$thisLine, $otherLine],
            ], $actor);

            $posted = $this->journalService->post($draft, $actor);

            if ($attachment) {
                $this->journalService->storeAttachment($posted, $attachment);
            }

            return $posted->fresh();
        });
    }

    public function assertTouchesAccount(Account $account, JournalEntry $entry): void
    {
        $touches = $entry->lines()->where('account_id', $account->id)->exists();

        if (! $touches) {
            abort(404);
        }
    }

    /**
     * @param  array{description: string, remove_attachment?: bool}  $data
     */
    public function updateMovementMeta(JournalEntry $entry, array $data, ?UploadedFile $attachment = null): JournalEntry
    {
        return $this->journalService->updatePostedMeta($entry, $data, $attachment);
    }

    public function voidMovement(JournalEntry $entry, User $actor, ?string $reason = null): JournalEntry
    {
        return $this->journalService->void($entry, $actor, $reason);
    }

    public function reverseMovement(JournalEntry $entry, User $actor): JournalEntry
    {
        return $this->journalService->reverse($entry, $actor);
    }

    /**
     * Balance is always derived from posted journal lines.
     */
    public function balance(Account $account): string
    {
        return $this->formatAmount($this->signedBalance($account));
    }

    /**
     * Posted ledger for one account with opening / running / closing balances.
     *
     * @param  array{date_from?: string|null, date_to?: string|null, voucher?: string|null, description?: string|null, amount?: float|int|string|null}  $filters
     * @return array{
     *     opening_balance: string,
     *     closing_balance: string,
     *     period_debit: string,
     *     period_credit: string,
     *     period_net: string,
     *     lines: LengthAwarePaginator
     * }
     */
    public function ledger(Account $account, array $filters = [], int $perPage = 50): array
    {
        $dateFrom = $this->nullableDate($filters['date_from'] ?? null);
        $dateTo = $this->nullableDate($filters['date_to'] ?? null);
        $search = [
            'voucher' => trim((string) ($filters['voucher'] ?? '')),
            'description' => trim((string) ($filters['description'] ?? '')),
            'amount' => $filters['amount'] ?? null,
        ];
        $hasSearch = $search['voucher'] !== ''
            || $search['description'] !== ''
            || ($search['amount'] !== null && $search['amount'] !== '');

        $opening = (! $hasSearch && $dateFrom)
            ? $this->signedBalance($account, beforeDate: $dateFrom)
            : 0.0;

        $page = max(1, (int) request()->integer('page', 1));
        $offset = ($page - 1) * $perPage;

        $priorTotals = $this->postedLineTotals(
            $account,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            limit: $offset,
            search: $search
        );

        $running = $opening + $this->signedFromTotals($account, $priorTotals['debit'], $priorTotals['credit']);

        $paginator = $this->postedLinesQuery($account, $dateFrom, $dateTo, search: $search)
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (JournalLine $line) use ($account, &$running): array {
                return $this->mapLedgerLine($account, $line, $running);
            });

        $periodTotals = $this->postedLineTotals($account, dateFrom: $dateFrom, dateTo: $dateTo, search: $search);
        $closing = $opening + $this->signedFromTotals(
            $account,
            $periodTotals['debit'],
            $periodTotals['credit']
        );

        return [
            'opening_balance' => $this->formatAmount($opening),
            'closing_balance' => $this->formatAmount($closing),
            'period_debit' => $this->formatAmount($periodTotals['debit']),
            'period_credit' => $this->formatAmount($periodTotals['credit']),
            'period_net' => $this->formatAmount($periodTotals['credit'] - $periodTotals['debit']),
            'lines' => $paginator,
        ];
    }

    /**
     * Full posted ledger for Excel / PDF (not paginated).
     *
     * @param  array{date_from?: string|null, date_to?: string|null, voucher?: string|null, description?: string|null, amount?: float|int|string|null}  $filters
     * @return array{
     *     account: array{id: int, code: string, name: string, type_label: string, currency: string},
     *     filters: array<string, mixed>,
     *     opening_balance: string,
     *     closing_balance: string,
     *     period_debit: string,
     *     period_credit: string,
     *     period_net: string,
     *     lines: list<array<string, mixed>>
     * }
     */
    public function ledgerExport(Account $account, array $filters = []): array
    {
        $dateFrom = $this->nullableDate($filters['date_from'] ?? null);
        $dateTo = $this->nullableDate($filters['date_to'] ?? null);
        $search = [
            'voucher' => trim((string) ($filters['voucher'] ?? '')),
            'description' => trim((string) ($filters['description'] ?? '')),
            'amount' => $filters['amount'] ?? null,
        ];
        $hasSearch = $search['voucher'] !== ''
            || $search['description'] !== ''
            || ($search['amount'] !== null && $search['amount'] !== '');

        $opening = (! $hasSearch && $dateFrom)
            ? $this->signedBalance($account, beforeDate: $dateFrom)
            : 0.0;

        $running = $opening;
        $lines = $this->postedLinesQuery($account, $dateFrom, $dateTo, search: $search)
            ->get()
            ->map(function (JournalLine $line) use ($account, &$running): array {
                return $this->mapLedgerLine($account, $line, $running);
            })
            ->values()
            ->all();

        $periodTotals = $this->postedLineTotals($account, dateFrom: $dateFrom, dateTo: $dateTo, search: $search);
        $closing = $opening + $this->signedFromTotals(
            $account,
            $periodTotals['debit'],
            $periodTotals['credit']
        );

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type_label' => $account->type->label(),
                'currency' => $account->currency->value,
            ],
            'filters' => $filters,
            'opening_balance' => $this->formatAmount($opening),
            'closing_balance' => $this->formatAmount($closing),
            'period_debit' => $this->formatAmount($periodTotals['debit']),
            'period_credit' => $this->formatAmount($periodTotals['credit']),
            'period_net' => $this->formatAmount($periodTotals['credit'] - $periodTotals['debit']),
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLedgerLine(Account $account, JournalLine $line, float &$running): array
    {
        $debit = (float) $line->debit;
        $credit = (float) $line->credit;
        $running += $this->signedFromTotals($account, $debit, $credit);
        $entry = $line->journalEntry;

        return [
            'id' => $line->id,
            'entry_date' => $entry?->entry_date?->format('Y-m-d'),
            'voucher_number' => $entry?->voucher_number,
            'journal_entry_id' => $entry?->id,
            'description' => $entry?->description,
            'reference' => $entry?->reference,
            'memo' => $line->memo,
            'attachment_url' => $entry?->attachmentUrl(),
            'has_attachment' => filled($entry?->attachment_path),
            'counterpart' => $this->counterpartAccount($line),
            'debit' => $this->formatAmount($debit),
            'credit' => $this->formatAmount($credit),
            'balance' => $this->formatAmount($running),
        ];
    }

    /**
     * Signed account balance from posted lines only.
     */
    private function signedBalance(Account $account, ?string $beforeDate = null, ?string $dateTo = null): float
    {
        $totals = $this->postedLineTotals($account, dateTo: $dateTo, beforeDate: $beforeDate);

        return $this->signedFromTotals($account, $totals['debit'], $totals['credit']);
    }

    /**
     * @return array{debit: float, credit: float}
     */
    private function postedLineTotals(
        Account $account,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $beforeDate = null,
        ?int $limit = null,
        array $search = []
    ): array {
        $query = $this->postedLinesQuery($account, $dateFrom, $dateTo, $beforeDate, $search);

        if ($limit !== null) {
            if ($limit <= 0) {
                return ['debit' => 0.0, 'credit' => 0.0];
            }

            $ids = (clone $query)->limit($limit)->pluck('journal_lines.id');

            if ($ids->isEmpty()) {
                return ['debit' => 0.0, 'credit' => 0.0];
            }

            $totals = JournalLine::query()
                ->whereIn('id', $ids)
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();
        } else {
            $totals = (clone $query)
                ->reorder()
                ->toBase()
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
                ->first();
        }

        return [
            'debit' => (float) ($totals->total_debit ?? 0),
            'credit' => (float) ($totals->total_credit ?? 0),
        ];
    }

    private function postedLinesQuery(
        Account $account,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $beforeDate = null,
        array $search = []
    ): Builder {
        $voucher = trim((string) ($search['voucher'] ?? ''));
        $description = trim((string) ($search['description'] ?? ''));
        $amount = $search['amount'] ?? null;

        return JournalLine::query()
            ->select('journal_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->whereNull('journal_lines.deleted_at')
            ->whereNull('journal_entries.deleted_at')
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->when($beforeDate, fn (Builder $query) => $query->whereDate('journal_entries.entry_date', '<', $beforeDate))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('journal_entries.entry_date', '<=', $dateTo))
            ->when($voucher !== '', function (Builder $query) use ($voucher): void {
                $query->where('journal_entries.voucher_number', 'like', '%'.$this->escapeLike($voucher).'%');
            })
            ->when($description !== '', function (Builder $query) use ($description): void {
                $like = '%'.$this->escapeLike($description).'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner
                        ->where('journal_entries.description', 'like', $like)
                        ->orWhere('journal_lines.memo', 'like', $like);
                });
            })
            ->when($amount !== null && $amount !== '', function (Builder $query) use ($amount): void {
                $value = round((float) $amount, 2);
                $query->where(function (Builder $inner) use ($value): void {
                    $inner
                        ->where('journal_lines.debit', $value)
                        ->orWhere('journal_lines.credit', $value);
                });
            })
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->with([
                'journalEntry:id,voucher_number,entry_date,description,reference,currency,status,attachment_path',
                'journalEntry.lines:id,journal_entry_id,account_id',
                'journalEntry.lines.account:id,code,name',
            ]);
    }

    /**
     * @return array{id: int, code: string, name: string, label: string}|null
     */
    private function counterpartAccount(JournalLine $line): ?array
    {
        $counterpart = $line->journalEntry?->lines
            ?->first(fn (JournalLine $other): bool => (int) $other->account_id !== (int) $line->account_id);

        $account = $counterpart?->account;

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

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function signedFromTotals(Account $account, float $debit, float $credit): float
    {
        return $account->type->isDebitNormal()
            ? $debit - $credit
            : $credit - $debit;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * Seed default chart of accounts.
     * Dubai (1300) and Ship Clearing (1500) use AED by design.
     */
    public function seedChartOfAccounts(): void
    {
        DB::transaction(function (): void {
            $tree = [
                ['code' => '1000', 'name' => 'Assets', 'type' => AccountType::Asset, 'currency' => Currency::USD, 'parent' => null],
                ['code' => '1100', 'name' => 'Cash', 'type' => AccountType::Asset, 'currency' => Currency::USD, 'parent' => '1000'],
                ['code' => '1200', 'name' => 'Bank', 'type' => AccountType::Asset, 'currency' => Currency::USD, 'parent' => '1000'],
                ['code' => '1300', 'name' => 'Dubai Account', 'type' => AccountType::Asset, 'currency' => Currency::AED, 'parent' => '1000'],
                ['code' => '1400', 'name' => 'Iran Account', 'type' => AccountType::Asset, 'currency' => Currency::USD, 'parent' => '1000'],
                ['code' => '1500', 'name' => 'Ship Clearing', 'type' => AccountType::Asset, 'currency' => Currency::AED, 'parent' => '1000'],
                ['code' => '1600', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'currency' => Currency::USD, 'parent' => '1000'],
                ['code' => '1660', 'name' => 'Iran Cars Receivable', 'type' => AccountType::Asset, 'currency' => Currency::USD, 'parent' => '1000'],
                ['code' => '2000', 'name' => 'Liabilities', 'type' => AccountType::Liability, 'currency' => Currency::USD, 'parent' => null],
                ['code' => '2100', 'name' => 'Accounts Payable', 'type' => AccountType::Liability, 'currency' => Currency::USD, 'parent' => '2000'],
                ['code' => '2210', 'name' => 'Ship Partner Clearing', 'type' => AccountType::Liability, 'currency' => Currency::USD, 'parent' => '2000'],
                ['code' => '2215', 'name' => 'Ship Partner Clearing AED', 'type' => AccountType::Liability, 'currency' => Currency::AED, 'parent' => '2000'],
                ['code' => '3000', 'name' => 'Equity', 'type' => AccountType::Equity, 'currency' => Currency::USD, 'parent' => null],
                ['code' => '4000', 'name' => 'Revenue', 'type' => AccountType::Revenue, 'currency' => Currency::USD, 'parent' => null],
                ['code' => '4100', 'name' => 'Shipping Revenue', 'type' => AccountType::Revenue, 'currency' => Currency::USD, 'parent' => '4000'],
                ['code' => '4200', 'name' => 'Land Transit Revenue', 'type' => AccountType::Revenue, 'currency' => Currency::USD, 'parent' => '4000'],
                ['code' => '4300', 'name' => 'Iran Cars Revenue', 'type' => AccountType::Revenue, 'currency' => Currency::USD, 'parent' => '4000'],
                ['code' => '5000', 'name' => 'Expenses', 'type' => AccountType::Expense, 'currency' => Currency::USD, 'parent' => null],
                ['code' => '5100', 'name' => 'Voyage Expenses', 'type' => AccountType::Expense, 'currency' => Currency::USD, 'parent' => '5000'],
                ['code' => '5110', 'name' => 'Ship Expenses USD', 'type' => AccountType::Expense, 'currency' => Currency::USD, 'parent' => '5000'],
                ['code' => '5200', 'name' => 'Ship Expenses', 'type' => AccountType::Expense, 'currency' => Currency::AED, 'parent' => '5000'],
                ['code' => '5300', 'name' => 'Captain Commission', 'type' => AccountType::Expense, 'currency' => Currency::USD, 'parent' => '5000'],
                ['code' => '5310', 'name' => 'Captain Commission AED', 'type' => AccountType::Expense, 'currency' => Currency::AED, 'parent' => '5000'],
            ];

            $ids = [];

            foreach ($tree as $item) {
                $parentId = $item['parent'] ? ($ids[$item['parent']] ?? null) : null;

                $account = Account::query()->updateOrCreate(
                    ['code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'type' => $item['type']->value,
                        'currency' => $item['currency']->value,
                        'parent_id' => $parentId,
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );

                $ids[$item['code']] = $account->id;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertParentCompatible(array $data, ?int $ignoreId = null): void
    {
        if (empty($data['parent_id'])) {
            return;
        }

        $parent = Account::query()->find($data['parent_id']);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent account not found.',
            ]);
        }

        if ($ignoreId && (int) $data['parent_id'] === $ignoreId) {
            throw ValidationException::withMessages([
                'parent_id' => 'An account cannot be its own parent.',
            ]);
        }

        if ($parent->type->value !== $data['type']) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent account type must match the account type.',
            ]);
        }

        if ($parent->currency->value !== $data['currency']) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent account currency must match the account currency.',
            ]);
        }
    }
}
