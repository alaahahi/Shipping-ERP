<?php

namespace App\Services;

use App\Enums\AppLocale;
use App\Enums\SettingKey;
use App\Models\Account;
use App\Support\ApplicationTimezone;
use App\Support\ResolvedLocale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountLedgerExportService
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly SettingService $settings
    ) {}

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, voucher?: string|null, description?: string|null, amount?: float|int|string|null}  $filters
     */
    public function excel(Account $account, array $filters): StreamedResponse
    {
        $payload = $this->accountService->ledgerExport($account, $filters);
        $labels = $this->labels();
        $filename = $this->filename($account, 'xlsx');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($account->code.' Ledger', 0, 31));
        $sheet->setRightToLeft($this->locale()->isRtl());

        $sheet->setCellValue('A1', $this->companyName());
        $sheet->setCellValue('A2', $labels['ledger']);
        $sheet->setCellValue('A3', $account->code.' — '.$account->name);
        $sheet->setCellValue('A4', $account->type->label().' · '.$account->currency->value);
        $sheet->setCellValue('A5', $this->periodLabel($filters, $labels));

        $sheet->setCellValue('A6', $labels['opening']);
        $sheet->setCellValue('B6', $payload['opening_balance']);
        $sheet->setCellValue('C6', $labels['debit']);
        $sheet->setCellValue('D6', $payload['period_debit']);
        $sheet->setCellValue('E6', $labels['credit']);
        $sheet->setCellValue('F6', $payload['period_credit']);
        $sheet->setCellValue('G6', $labels['period_net']);
        $sheet->setCellValue('H6', $payload['period_net']);
        $sheet->setCellValue('A7', $labels['closing']);
        $sheet->setCellValue('B7', $payload['closing_balance']);
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A6:H7')->getFont()->setBold(true);

        $headers = [
            $labels['date'],
            $labels['voucher'],
            $labels['description'],
            $labels['source'],
            $labels['debit'],
            $labels['credit'],
            $labels['balance'],
        ];

        $headerRow = 9;
        foreach ($headers as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue("{$column}{$headerRow}", $header);
        }
        $sheet->getStyle('A9:G9')->getFont()->setBold(true);

        $line = 10;
        foreach ($payload['lines'] as $row) {
            $sheet->fromArray([
                $row['entry_date'] ?? '',
                $row['voucher_number'] ?? '',
                trim((string) ($row['description'] ?? '').(filled($row['memo'] ?? null) ? ' / '.$row['memo'] : '')),
                $row['counterpart']['label'] ?? '',
                $row['debit'],
                $row['credit'],
                $row['balance'],
            ], null, "A{$line}");
            $line++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, voucher?: string|null, description?: string|null, amount?: float|int|string|null}  $filters
     */
    public function pdf(Account $account, array $filters): Response
    {
        $payload = $this->accountService->ledgerExport($account, $filters);
        $labels = $this->labels(AppLocale::English);

        $pdf = Pdf::loadView('reports.account-ledger-pdf', [
            'company' => $this->companyName(),
            'payload' => $payload,
            'filters' => $filters,
            'labels' => $labels,
            'period' => $this->periodLabel($filters, $labels),
            'rtl' => false,
            'locale' => AppLocale::English->value,
            'generated_at' => ApplicationTimezone::formatNowLabel(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($account, 'pdf'));
    }

    /**
     * @return array<string, string>
     */
    private function labels(?AppLocale $locale = null): array
    {
        return match ($locale ?? $this->locale()) {
            AppLocale::English => [
                'ledger' => 'Account statement',
                'generated' => 'Generated',
                'period' => 'Period',
                'all_dates' => 'All dates',
                'opening' => 'Opening',
                'closing' => 'Closing',
                'date' => 'Date',
                'voucher' => 'Voucher',
                'description' => 'Description',
                'source' => 'From account',
                'debit' => 'Debit',
                'credit' => 'Credit',
                'balance' => 'Balance',
                'period_net' => 'Net',
                'empty' => 'No posted activity in this period.',
            ],
            AppLocale::KurdishSorani => [
                'ledger' => 'پەڕەی هەژمار',
                'generated' => 'دەرچوو',
                'period' => 'ماوە',
                'all_dates' => 'هەموو بەروارەکان',
                'opening' => 'دەستپێک',
                'closing' => 'کۆتایی',
                'date' => 'بەروار',
                'voucher' => 'پسوڵە',
                'description' => 'وەسف',
                'source' => 'سەرچاوە',
                'debit' => 'قەرز',
                'credit' => 'قەرزپێچەوانە',
                'balance' => 'باڵانس',
                'period_net' => 'ئەنجام',
                'empty' => 'هیچ جووڵەیەک نییە.',
            ],
            default => [
                'ledger' => 'كشف الحساب',
                'generated' => 'تاريخ الإصدار',
                'period' => 'الفترة',
                'all_dates' => 'كل التواريخ',
                'opening' => 'افتتاحي',
                'closing' => 'ختامي',
                'date' => 'التاريخ',
                'voucher' => 'السند',
                'description' => 'الوصف',
                'source' => 'مصدر المبلغ',
                'debit' => 'مدين',
                'credit' => 'دائن',
                'balance' => 'الرصيد',
                'period_net' => 'الناتج',
                'empty' => 'لا توجد حركات مرحلة في هذه الفترة.',
            ],
        };
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @param  array<string, string>  $labels
     */
    private function periodLabel(array $filters, array $labels): string
    {
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));

        if ($from === '' && $to === '') {
            return $labels['all_dates'];
        }

        return $labels['period'].': '.($from !== '' ? $from : '…').' → '.($to !== '' ? $to : '…');
    }

    private function filename(Account $account, string $extension): string
    {
        $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', $account->code) ?: 'account';

        return 'ledger-'.$code.'-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function companyName(): string
    {
        return (string) $this->settings->get(SettingKey::CompanyName, 'Shipping ERP');
    }

    private function locale(): AppLocale
    {
        return AppLocale::tryFrom(ResolvedLocale::fromRequest(request())) ?? AppLocale::Arabic;
    }
}
