<?php

namespace App\Services;

use App\Models\Ship;
use App\Models\ShipOwnership;

class ShipPartnerSettlementService
{
    /**
     * Managing owner covers unassigned expenses (spreadsheet residual).
     * An expense paid by another owner counts in that owner's payments.
     * Spender implied = total expenses − other partners' (installments + expenses they paid).
     *
     * @return array<string, mixed>
     */
    public function summary(Ship $ship, string $currency = 'USD'): array
    {
        $ship->loadMissing(['ownerships.owner']);

        $expenses = $ship->expenses()->where('currency', $currency)->get(['amount', 'paid_by_owner_id']);
        $totalExpenses = round((float) $expenses->sum('amount'), 2);

        $contributions = $ship->partnerContributions()
            ->where('currency', $currency)
            ->get()
            ->groupBy('owner_id')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2));

        $spender = $ship->ownerships->firstWhere('is_managing', true)
            ?? $ship->ownerships->sortByDesc('share_percent')->first();
        $spenderOwnerId = $spender?->owner_id ? (int) $spender->owner_id : null;

        $expensePaidByOwner = [];
        foreach ($expenses as $expense) {
            $ownerId = $expense->paid_by_owner_id
                ? (int) $expense->paid_by_owner_id
                : $spenderOwnerId;
            if (! $ownerId) {
                continue;
            }
            $expensePaidByOwner[$ownerId] = round(
                ($expensePaidByOwner[$ownerId] ?? 0) + (float) $expense->amount,
                2
            );
        }

        $othersPaid = 0.0;
        $partners = [];

        foreach ($ship->ownerships as $ownership) {
            /** @var ShipOwnership $ownership */
            $ownerId = (int) $ownership->owner_id;
            $isSpender = $spenderOwnerId !== null && $ownerId === $spenderOwnerId;
            $installments = (float) ($contributions[$ownerId] ?? 0);
            $directExpenses = (float) ($expensePaidByOwner[$ownerId] ?? 0);

            if ($isSpender) {
                $paid = 0.0;
            } else {
                $paid = round($installments + $directExpenses, 2);
                $othersPaid = round($othersPaid + $paid, 2);
            }

            $partners[] = [
                'ownership_id' => $ownership->id,
                'owner_id' => $ownerId,
                'owner_name' => $ownership->owner?->name,
                'share_percent' => number_format((float) $ownership->share_percent, 2, '.', ''),
                'is_spender' => $isSpender,
                'installments' => number_format($installments, 2, '.', ''),
                'direct_expenses' => number_format($isSpender ? 0.0 : $directExpenses, 2, '.', ''),
                'paid' => $paid,
            ];
        }

        $spenderImplied = round($totalExpenses - $othersPaid, 2);

        foreach ($partners as &$partner) {
            if ($partner['is_spender']) {
                $partner['paid'] = $spenderImplied;
                $partner['direct_expenses'] = number_format(
                    (float) ($expensePaidByOwner[(int) $partner['owner_id']] ?? 0),
                    2,
                    '.',
                    ''
                );
            }
            $fair = round($totalExpenses * ((float) $partner['share_percent'] / 100), 2);
            $partner['fair_share'] = number_format($fair, 2, '.', '');
            $partner['paid_formatted'] = number_format((float) $partner['paid'], 2, '.', '');
            $partner['variance'] = number_format((float) $partner['paid'] - $fair, 2, '.', '');
        }
        unset($partner);

        $difference = round($spenderImplied - $othersPaid, 2);

        return [
            'currency' => $currency,
            'total_expenses' => number_format($totalExpenses, 2, '.', ''),
            'spender_owner_id' => $spenderOwnerId,
            'spender_name' => $spender?->owner?->name,
            'spender_paid' => number_format($spenderImplied, 2, '.', ''),
            'others_paid' => number_format($othersPaid, 2, '.', ''),
            'difference' => number_format($difference, 2, '.', ''),
            'difference_numeric' => $difference,
            'other_name' => data_get(collect($partners)->first(fn (array $row) => ! $row['is_spender']), 'owner_name'),
            'hint_key' => abs($difference) < 0.005
                ? 'settled'
                : ($difference < 0 ? 'other_more' : 'spender_more'),
            'partners' => $partners,
        ];
    }
}
