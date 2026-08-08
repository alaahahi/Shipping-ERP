<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\JournalStatus;
use App\Models\Company;
use App\Models\JournalLine;
use App\Models\Voyage;

class CompanyLedgerService
{
    public function __construct(
        private readonly CompanyReceivableAccountService $companyReceivableAccounts
    ) {}

    /**
     * Company AR statement from posted lines on 1600 and the company's subsidiary AR account.
     *
     * @return array{
     *     currency: string,
     *     open_balance: string,
     *     total_debit: string,
     *     total_credit: string,
     *     movements: list<array<string, mixed>>
     * }
     */
    public function statement(Company $company, ?int $voyageId = null): array
    {
        $accountIds = $this->companyReceivableAccounts->receivableAccountIds($company);

        $query = JournalLine::query()
            ->with([
                'journalEntry:id,voucher_number,entry_date,status,description,reference',
                'voyage:id,voyage_number',
            ])
            ->where('company_id', $company->id)
            ->whereIn('account_id', $accountIds)
            ->whereHas(
                'journalEntry',
                fn ($entry) => $entry
                    ->where('status', JournalStatus::Posted->value)
                    ->where('currency', Currency::USD->value)
            );

        if ($voyageId) {
            $query->where('voyage_id', $voyageId);
        }

        $lines = $query->get()->sortBy([
            fn (JournalLine $line) => $line->journalEntry?->entry_date?->format('Y-m-d') ?? '',
            fn (JournalLine $line) => $line->id,
        ])->values();

        $running = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $movements = [];

        foreach ($lines as $line) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);
            $totalDebit = round($totalDebit + $debit, 2);
            $totalCredit = round($totalCredit + $credit, 2);
            $running = round($running + $debit - $credit, 2);

            $movements[] = [
                'id' => $line->id,
                'date' => $line->journalEntry?->entry_date?->format('Y-m-d'),
                'voucher' => $line->journalEntry?->voucher_number,
                'journal_entry_id' => $line->journal_entry_id,
                'reference' => $line->journalEntry?->reference,
                'memo' => $line->memo ?: $line->journalEntry?->description,
                'voyage_id' => $line->voyage_id,
                'voyage_number' => $line->voyage?->voyage_number,
                'debit' => number_format($debit, 2, '.', ''),
                'credit' => number_format($credit, 2, '.', ''),
                'balance' => number_format($running, 2, '.', ''),
            ];
        }

        return [
            'currency' => Currency::USD->value,
            'open_balance' => number_format($running, 2, '.', ''),
            'total_debit' => number_format($totalDebit, 2, '.', ''),
            'total_credit' => number_format($totalCredit, 2, '.', ''),
            'movements' => $movements,
        ];
    }

    /**
     * Movements on a voyage's company AR (simple voyage account view).
     *
     * @return list<array<string, mixed>>
     */
    public function voyageMovements(Voyage $voyage): array
    {
        $accountIds = $this->companyReceivableAccounts->receivableAccountIds();

        $lines = JournalLine::query()
            ->with([
                'journalEntry:id,voucher_number,entry_date,status,description,reference',
                'company:id,name',
            ])
            ->where('voyage_id', $voyage->id)
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('company_id')
            ->whereHas(
                'journalEntry',
                fn ($entry) => $entry->where('status', JournalStatus::Posted->value)
            )
            ->get()
            ->sortBy([
                fn (JournalLine $line) => $line->journalEntry?->entry_date?->format('Y-m-d') ?? '',
                fn (JournalLine $line) => $line->id,
            ])
            ->values();

        return $lines->map(function (JournalLine $line) {
            $debit = round((float) $line->debit, 2);
            $credit = round((float) $line->credit, 2);

            return [
                'id' => $line->id,
                'date' => $line->journalEntry?->entry_date?->format('Y-m-d'),
                'voucher' => $line->journalEntry?->voucher_number,
                'journal_entry_id' => $line->journal_entry_id,
                'company_id' => $line->company_id,
                'company_name' => $line->company?->name,
                'memo' => $line->memo ?: $line->journalEntry?->description,
                'debit' => number_format($debit, 2, '.', ''),
                'credit' => number_format($credit, 2, '.', ''),
                'kind' => $debit > 0 ? 'charge' : 'receipt',
            ];
        })->all();
    }
}
