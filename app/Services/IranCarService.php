<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\IranBorder;
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
     * @param  array{search?: string|null, company_id?: string|null, border?: string|null, remaining_only?: bool}  $filters
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
            $cars = $cars->filter(fn (IranCar $car) => $car->remainingAmount() > 0.009)->values();
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
                'total_amount' => $this->formatAmount($rows->sum(fn (IranCar $car) => (float) $car->total_amount)),
                'paid_amount' => $this->formatAmount($rows->sum(fn (IranCar $car) => $car->paidAmount())),
                'remaining_amount' => $this->formatAmount($rows->sum(fn (IranCar $car) => $car->remainingAmount())),
                'cars' => $transformed,
            ];
        }

        return $groups;
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
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data, User $actor): IranCar
    {
        return DB::transaction(function () use ($data, $actor): IranCar {
            $car = IranCar::query()->create([
                'company_id' => $data['company_id'],
                'border' => $data['border'],
                'vin' => $this->normalizeVin($data['vin']),
                'model_name' => trim($data['model_name']),
                'year' => $data['year'] ?? null,
                'color' => $this->nullableString($data['color'] ?? null),
                'currency' => Currency::USD->value,
                'total_amount' => round((float) ($data['total_amount'] ?? 0), 2),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'status' => IranCarStatus::Open->value,
                'created_by' => $actor->id,
            ]);

            $this->syncInvoiceJournal($car->fresh(), $actor);

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
        $newTotal = round((float) ($data['total_amount'] ?? $car->total_amount), 2);
        $newCompanyId = (int) $data['company_id'];

        if ($car->isTotalLocked()) {
            if ($newTotal !== round((float) $car->total_amount, 2)) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Total cannot be changed after payments have been recorded.',
                ]);
            }

            if ($newCompanyId !== (int) $car->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'Company cannot be changed after payments have been recorded.',
                ]);
            }
        }

        return DB::transaction(function () use ($car, $data, $actor, $newTotal, $newCompanyId): IranCar {
            $car->update([
                'company_id' => $newCompanyId,
                'border' => $data['border'],
                'vin' => $this->normalizeVin($data['vin']),
                'model_name' => trim($data['model_name']),
                'year' => $data['year'] ?? null,
                'color' => $this->nullableString($data['color'] ?? null),
                'total_amount' => $newTotal,
                'notes' => $this->nullableString($data['notes'] ?? null),
            ]);

            if (! $car->isTotalLocked()) {
                $this->syncInvoiceJournal($car->fresh(), $actor);
            }

            $this->refreshStatus($car->fresh());

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
            'paid_amount' => $this->formatAmount($paid),
            'remaining_amount' => $this->formatAmount($remaining),
            'notes' => $car->notes,
            'status' => $car->status->value,
            'status_label' => $car->status->label(),
            'status_tone' => $car->status->tone(),
            'invoice_journal_id' => $car->invoice_journal_id,
            'invoice_voucher' => $car->invoiceJournal?->voucher_number,
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

        $total = round((float) $car->total_amount, 2);
        $remaining = $car->remainingAmount();
        $status = ($total > 0 && $remaining <= 0.009)
            ? IranCarStatus::Paid
            : IranCarStatus::Open;

        if ($car->status !== $status) {
            $car->update(['status' => $status->value]);
        }
    }

    /**
     * @param  array{search?: string|null, company_id?: string|null, border?: string|null, remaining_only?: bool}  $filters
     */
    public function exportExcel(array $filters = []): StreamedResponse
    {
        $groups = $this->grouped($filters);
        $filename = 'iran-cars-'.now()->format('Ymd-His').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Iran Cars');

        $headers = ['#', 'Border', 'Vehicle Model', 'Year', 'Color', 'VIN', 'Company', 'Total', 'Paid', 'Remaining', 'Status'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $line = 2;
        foreach ($groups as $group) {
            $sheet->setCellValue("A{$line}", $group['label']);
            $sheet->mergeCells("A{$line}:K{$line}");
            $sheet->getStyle("A{$line}")->getFont()->setBold(true);
            $line++;

            foreach ($group['cars'] as $car) {
                $sheet->fromArray([
                    $car['index'],
                    $car['border_label'],
                    $car['model_name'],
                    $car['year'],
                    $car['color'],
                    $car['vin'],
                    $car['company_name'],
                    $car['total_amount'],
                    $car['paid_amount'],
                    $car['remaining_amount'],
                    $car['status_label'],
                ], null, "A{$line}");
                $line++;
            }

            $line++;
        }

        foreach (range('A', 'K') as $column) {
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
     * @param  array{search?: string|null, company_id?: string|null, border?: string|null, remaining_only?: bool}  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = IranCar::query();

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
        $total = round((float) $car->total_amount, 2);
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
        $memo = sprintf('Iran car %s — %s', $car->vin, $company->name);

        $draft = $this->journalService->createDraft([
            'entry_date' => now()->toDateString(),
            'currency' => Currency::USD->value,
            'reference' => $car->vin,
            'description' => sprintf('Iran car invoice — %s', $car->vin),
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
