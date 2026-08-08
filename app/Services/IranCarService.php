<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\IranBorder;
use App\Enums\IranCarSaleState;
use App\Enums\IranCarStatus;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\Company;
use App\Models\IranCar;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IranCarService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly IranCarReceivableAccountService $iranReceivableAccounts
    ) {}

    /**
     * @param  array{search?: string|null, company_id?: string|null, border?: string|null, sale_state?: string|null, remaining_only?: bool}  $filters
     * @return list<array<string, mixed>>
     */
    public function grouped(array $filters = []): array
    {
        $cars = $this->filteredQuery($filters)
            ->with(['company:id,name', 'invoiceJournal:id,voucher_number,status'])
            ->withSum('payments as payments_sum_amount', 'amount')
            ->withCount('payments')
            ->orderByRaw("CASE border WHEN 'amir_abad' THEN 1 WHEN 'jolfa' THEN 2 WHEN 'bazargan' THEN 3 ELSE 4 END")
            ->orderBy('id')
            ->get();

        if (! empty($filters['remaining_only'])) {
            $cars = $cars->filter(fn (IranCar $car) => $car->isSold() && $car->remainingAmount() > 0.009)->values();
        }

        $groups = [];

        foreach (IranBorder::cases() as $border) {
            $rows = $cars->filter(fn (IranCar $car) => $car->border === $border)->values();
            if ($rows->isEmpty()) {
                continue;
            }

            $transformed = $rows->map(fn (IranCar $car, int $index) => $this->transform($car, index: $index + 1))->all();

            $groups[] = [
                'border' => $border->value,
                'label' => $border->label(),
                'count' => count($transformed),
                'list_amount' => $this->formatAmount($rows->sum(fn (IranCar $car) => (float) $car->total_amount)),
                'sale_amount' => $this->formatAmount($rows->sum(fn (IranCar $car) => $car->billedAmount())),
                'paid_amount' => $this->formatAmount($rows->sum(fn (IranCar $car) => $car->paidAmount())),
                'remaining_amount' => $this->formatAmount($rows->sum(fn (IranCar $car) => $car->remainingAmount())),
                'cars' => $transformed,
            ];
        }

        return $groups;
    }

    /**
     * @return array{unsold: int, sold: int}
     */
    public function saleStateCounts(): array
    {
        return [
            'unsold' => IranCar::query()->where('sale_state', IranCarSaleState::Unsold->value)->count(),
            'sold' => IranCar::query()->where('sale_state', IranCarSaleState::Sold->value)->count(),
        ];
    }

    /**
     * @param  array{
     *     company_id: int,
     *     border: string,
     *     vin: string,
     *     model_name: string,
     *     year?: int|null,
     *     color?: string|null,
     *     total_amount?: float|int|string|null,
     *     sale_price?: float|int|string|null,
     *     sale_state?: string|null,
     *     sold_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data, User $actor): IranCar
    {
        return DB::transaction(function () use ($data, $actor): IranCar {
            $saleState = IranCarSaleState::tryFrom((string) ($data['sale_state'] ?? IranCarSaleState::Unsold->value))
                ?? IranCarSaleState::Unsold;
            $listAmount = round((float) ($data['total_amount'] ?? 0), 2);
            $isSold = $saleState === IranCarSaleState::Sold;
            $salePrice = $isSold
                ? round((float) ($data['sale_price'] ?? $listAmount), 2)
                : null;

            $car = IranCar::query()->create([
                'company_id' => $data['company_id'],
                'border' => $data['border'],
                'vin' => $this->normalizeVin($data['vin']),
                'model_name' => trim($data['model_name']),
                'year' => $data['year'] ?? null,
                'color' => $this->nullableString($data['color'] ?? null),
                'currency' => Currency::USD->value,
                'total_amount' => $listAmount,
                'sale_price' => $salePrice,
                'notes' => $this->nullableString($data['notes'] ?? null),
                'status' => IranCarStatus::Open->value,
                'sale_state' => $saleState->value,
                'sold_at' => $isSold ? ($data['sold_at'] ?? now()->toDateString()) : null,
                'sold_by' => $isSold ? $actor->id : null,
                'created_by' => $actor->id,
            ]);

            if ($isSold) {
                $this->syncInvoiceJournal($car->fresh(), $actor);
            }

            return $car->fresh($this->defaultRelations());
        });
    }

    /**
     * @param  array{
     *     company_id: int,
     *     border: string,
     *     vin: string,
     *     model_name: string,
     *     year?: int|null,
     *     color?: string|null,
     *     total_amount?: float|int|string|null,
     *     sale_price?: float|int|string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(IranCar $car, array $data, User $actor): IranCar
    {
        if ($car->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Cancelled Iran cars cannot be edited.',
            ]);
        }

        $car->loadCount('payments');
        $newList = round((float) ($data['total_amount'] ?? $car->total_amount), 2);
        $newCompanyId = (int) $data['company_id'];
        $newSalePrice = array_key_exists('sale_price', $data)
            ? round((float) $data['sale_price'], 2)
            : round((float) ($car->sale_price ?? 0), 2);

        if ($car->isTotalLocked()) {
            if ($car->isSold() && $newSalePrice !== round((float) ($car->sale_price ?? 0), 2)) {
                throw ValidationException::withMessages([
                    'sale_price' => 'Sale price cannot be changed after payments have been recorded.',
                ]);
            }

            if ($newCompanyId !== (int) $car->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company cannot be changed after payments have been recorded.',
                ]);
            }
        }

        return DB::transaction(function () use ($car, $data, $actor, $newList, $newCompanyId, $newSalePrice): IranCar {
            $payload = [
                'company_id' => $newCompanyId,
                'border' => $data['border'],
                'vin' => $this->normalizeVin($data['vin']),
                'model_name' => trim($data['model_name']),
                'year' => $data['year'] ?? null,
                'color' => $this->nullableString($data['color'] ?? null),
                'total_amount' => $newList,
                'notes' => $this->nullableString($data['notes'] ?? null),
            ];

            if ($car->isSold()) {
                $payload['sale_price'] = $newSalePrice;
            }

            $car->update($payload);

            if ($car->isSold() && ! $car->isTotalLocked()) {
                $this->syncInvoiceJournal($car->fresh(), $actor);
            }

            $this->refreshStatus($car->fresh());

            return $car->fresh($this->defaultRelations());
        });
    }

    /**
     * @param  array{sale_price: float|int|string, sold_at: string, notes?: string|null}  $data
     */
    public function markSold(IranCar $car, array $data, User $actor): IranCar
    {
        if ($car->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Cancelled Iran cars cannot be sold.',
            ]);
        }

        if ($car->isSold()) {
            throw ValidationException::withMessages([
                'sale_state' => 'This car is already sold.',
            ]);
        }

        $salePrice = round((float) $data['sale_price'], 2);
        if ($salePrice < 0) {
            throw ValidationException::withMessages([
                'sale_price' => 'Sale price cannot be negative.',
            ]);
        }

        return DB::transaction(function () use ($car, $data, $actor, $salePrice): IranCar {
            $notes = $this->nullableString($data['notes'] ?? null);
            $mergedNotes = $notes
                ? trim((string) $car->notes.($car->notes ? "\n" : '').$notes)
                : $car->notes;

            $car->update([
                'sale_state' => IranCarSaleState::Sold->value,
                'sale_price' => $salePrice,
                'sold_at' => $data['sold_at'],
                'sold_by' => $actor->id,
                'notes' => $mergedNotes,
            ]);

            $this->syncInvoiceJournal($car->fresh(), $actor);

            return $car->fresh($this->defaultRelations());
        });
    }

    public function delete(IranCar $car, User $actor): void
    {
        $car->loadCount('payments');

        if ($car->isTotalLocked()) {
            throw ValidationException::withMessages([
                'car' => 'Reverse all payments before deleting this car.',
            ]);
        }

        DB::transaction(function () use ($car, $actor): void {
            $this->voidInvoiceJournal($car, $actor, 'Iran car deleted.');

            Log::info('Iran car deleted.', [
                'iran_car_id' => $car->id,
                'vin' => $car->vin,
                'company_id' => $car->company_id,
                'deleted_by' => $actor->id,
            ]);

            $car->delete();
        });
    }

    /**
     * @return list<array{id: int, label: string, code: string}>
     */
    public function cashBankAccountOptions(): array
    {
        $parents = Account::query()
            ->whereIn('code', ['1100', '1200'])
            ->where('currency', Currency::USD->value)
            ->get(['id', 'code']);

        $parentIds = $parents->pluck('id')->all();

        return Account::query()
            ->where('is_active', true)
            ->where('type', AccountType::Asset->value)
            ->where('currency', Currency::USD->value)
            ->where(function ($query) use ($parentIds): void {
                $query->whereIn('code', ['1100', '1200']);
                if ($parentIds !== []) {
                    $query->orWhereIn('parent_id', $parentIds);
                }
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'label' => $account->code.' — '.$account->name,
            ])
            ->all();
    }

    public function resolveCashBankAccount(int $accountId): Account
    {
        $allowed = collect($this->cashBankAccountOptions())->pluck('id');
        $account = Account::query()->whereKey($accountId)->first();

        if (! $account || ! $account->is_active || ! $allowed->contains($account->id)) {
            throw ValidationException::withMessages([
                'debit_account_id' => 'Select an active USD cash or bank account (1100 / 1200).',
            ]);
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(IranCar $car, bool $detailed = false, ?int $index = null): array
    {
        $car->loadMissing([
            'company:id,name',
            'invoiceJournal:id,voucher_number,status',
            'creator:id,name',
            'seller:id,name',
        ]);

        if ($detailed) {
            $car->loadMissing([
                'payments' => fn ($query) => $query
                    ->with([
                        'debitAccount:id,code,name',
                        'journalEntry:id,voucher_number,status',
                        'creator:id,name',
                    ])
                    ->latest('payment_date')
                    ->latest('id'),
            ]);
        }

        $paid = $car->paidAmount();
        $remaining = $car->remainingAmount();

        $payload = [
            'id' => $car->id,
            'index' => $index,
            'company_id' => $car->company_id,
            'company_name' => $car->company?->name,
            'border' => $car->border->value,
            'border_label' => $car->border->label(),
            'vin' => $car->vin,
            'model_name' => $car->model_name,
            'year' => $car->year,
            'color' => $car->color,
            'currency' => $car->currency->value,
            'total_amount' => $this->formatAmount((float) $car->total_amount),
            'sale_price' => $car->sale_price === null ? null : $this->formatAmount((float) $car->sale_price),
            'paid_amount' => $this->formatAmount($paid),
            'remaining_amount' => $this->formatAmount($remaining),
            'notes' => $car->notes,
            'status' => $car->status->value,
            'status_label' => $car->status->label(),
            'status_tone' => $car->status->tone(),
            'sale_state' => $car->sale_state->value,
            'sale_state_label' => $car->sale_state->label(),
            'sale_state_tone' => $car->sale_state->tone(),
            'sold_at' => $car->sold_at?->toDateString(),
            'sold_by' => $car->seller?->name,
            'invoice_journal_id' => $car->invoice_journal_id,
            'invoice_voucher' => $car->invoiceJournal?->voucher_number,
            'is_sold' => $car->isSold(),
            'is_total_locked' => $car->isTotalLocked(),
            'created_at' => $car->created_at?->toDateString(),
        ];

        if ($detailed) {
            $payload['payments'] = $car->payments
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'voucher_number' => $payment->voucher_number,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'amount' => $this->formatAmount((float) $payment->amount),
                    'currency' => $payment->currency->value,
                    'debit_account' => $payment->debitAccount?->code
                        ? $payment->debitAccount->code.' — '.$payment->debitAccount->name
                        : null,
                    'journal_entry_id' => $payment->journal_entry_id,
                    'journal_voucher' => $payment->journalEntry?->voucher_number,
                    'reference' => $payment->reference,
                    'notes' => $payment->notes,
                    'created_by' => $payment->creator?->name,
                ])
                ->all();
        }

        return $payload;
    }

    public function refreshStatus(IranCar $car): void
    {
        if ($car->status === IranCarStatus::Cancelled) {
            return;
        }

        if (! $car->isSold()) {
            if ($car->status !== IranCarStatus::Open) {
                $car->update(['status' => IranCarStatus::Open->value]);
            }

            return;
        }

        $total = $car->billedAmount();
        $remaining = $car->remainingAmount();
        $status = ($total > 0 && $remaining <= 0.009)
            ? IranCarStatus::Paid
            : IranCarStatus::Open;

        if ($car->status !== $status) {
            $car->update(['status' => $status->value]);
        }
    }

    /**
     * @param  array{search?: string|null, company_id?: string|null, border?: string|null, sale_state?: string|null, remaining_only?: bool}  $filters
     */
    public function exportExcel(array $filters = []): StreamedResponse
    {
        $groups = $this->grouped($filters);
        $saleState = $filters['sale_state'] ?? IranCarSaleState::Unsold->value;
        $filename = 'iran-cars-'.$saleState.'-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($saleState === IranCarSaleState::Sold->value ? 'Sold' : 'Unsold');

        $isSold = $saleState === IranCarSaleState::Sold->value;
        $headers = $isSold
            ? ['#', 'Border', 'Vehicle Model', 'Year', 'Color', 'VIN', 'Company', 'Sale price', 'Paid', 'Remaining', 'Sold at', 'Status']
            : ['#', 'Border', 'Vehicle Model', 'Year', 'Color', 'VIN', 'Company', 'List price', 'Status'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }
        $lastCol = $isSold ? 'L' : 'I';
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

        $line = 2;
        foreach ($groups as $group) {
            $sheet->setCellValue("A{$line}", $group['label']);
            $sheet->mergeCells("A{$line}:{$lastCol}{$line}");
            $sheet->getStyle("A{$line}")->getFont()->setBold(true);
            $line++;

            foreach ($group['cars'] as $car) {
                $row = [
                    $car['index'],
                    $car['border_label'],
                    $car['model_name'],
                    $car['year'],
                    $car['color'],
                    $car['vin'],
                    $car['company_name'],
                ];

                if ($isSold) {
                    $row[] = $car['sale_price'];
                    $row[] = $car['paid_amount'];
                    $row[] = $car['remaining_amount'];
                    $row[] = $car['sold_at'];
                    $row[] = $car['status_label'];
                } else {
                    $row[] = $car['total_amount'];
                    $row[] = $car['sale_state_label'];
                }

                $sheet->fromArray($row, null, "A{$line}");
                $line++;
            }

            $line++;
        }

        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function normalizeVin(string $vin): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', trim($vin)));
    }

    /**
     * @param  array{search?: string|null, company_id?: string|null, border?: string|null, sale_state?: string|null, remaining_only?: bool}  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = IranCar::query();

        $saleState = IranCarSaleState::tryFrom((string) ($filters['sale_state'] ?? IranCarSaleState::Unsold->value))
            ?? IranCarSaleState::Unsold;
        $query->where('sale_state', $saleState->value);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('vin', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (! empty($filters['border'])) {
            $query->where('border', $filters['border']);
        }

        return $query;
    }

    private function syncInvoiceJournal(IranCar $car, User $actor): void
    {
        if (! $car->isSold()) {
            $this->voidInvoiceJournal($car, $actor, 'Iran car returned to unsold inventory.');
            $this->refreshStatus($car->fresh());

            return;
        }

        $total = $car->billedAmount();
        $postedInvoice = $car->invoiceJournal && $car->invoiceJournal->status === JournalStatus::Posted
            ? $car->invoiceJournal
            : null;

        if ($postedInvoice && $car->isTotalLocked()) {
            return;
        }

        $needsRepost = true;
        if ($postedInvoice) {
            $debitLine = $postedInvoice->lines()->where('debit', '>', 0)->first();
            $sameAmount = $debitLine && round((float) $debitLine->debit, 2) === $total;
            $sameCompany = $debitLine && (int) $debitLine->company_id === (int) $car->company_id;
            $needsRepost = ! ($sameAmount && $sameCompany);
        }

        if (! $needsRepost) {
            $this->refreshStatus($car);

            return;
        }

        $this->voidInvoiceJournal($car, $actor, 'Iran car invoice reversed before payments.');

        if ($total <= 0) {
            $this->refreshStatus($car->fresh());

            return;
        }

        $company = Company::query()->findOrFail($car->company_id);
        $receivable = $this->iranReceivableAccounts->resolveFor($company);
        $revenue = $this->iranReceivableAccounts->revenueAccount();
        $memo = sprintf('Iran car sale %s — %s', $car->vin, $company->name);

        $draft = $this->journalService->createDraft([
            'entry_date' => $car->sold_at?->toDateString() ?? now()->toDateString(),
            'currency' => Currency::USD->value,
            'reference' => $car->vin,
            'description' => sprintf('Iran car sale — %s', $car->vin),
            'lines' => [
                [
                    'account_id' => $receivable->id,
                    'company_id' => $company->id,
                    'debit' => $total,
                    'credit' => 0,
                    'memo' => $memo,
                ],
                [
                    'account_id' => $revenue->id,
                    'company_id' => $company->id,
                    'debit' => 0,
                    'credit' => $total,
                    'memo' => '4300 Iran Cars Revenue',
                ],
            ],
        ], $actor);

        $posted = $this->journalService->post($draft, $actor);

        $car->update(['invoice_journal_id' => $posted->id]);
        $this->refreshStatus($car->fresh());
    }

    private function voidInvoiceJournal(IranCar $car, User $actor, string $reason): void
    {
        $car->loadMissing('invoiceJournal.lines');
        $journal = $car->invoiceJournal;

        if ($journal && $journal->isPosted()) {
            $this->journalService->void($journal, $actor, $reason);
        }

        if ($car->invoice_journal_id) {
            $car->update(['invoice_journal_id' => null]);
        }
    }

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return [
            'company:id,name',
            'invoiceJournal:id,voucher_number,status',
            'creator:id,name',
            'seller:id,name',
        ];
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
