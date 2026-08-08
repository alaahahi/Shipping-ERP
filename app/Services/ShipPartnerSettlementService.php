<?php

namespace App\Services;

use App\Models\Ship;
use App\Models\ShipOwnership;

class ShipPartnerSettlementService
{
    /**
     * Spender (managing owner) is treated as paying all expenses.
     * Other owners record installment contributions.
     * Spender implied paid = total expenses − others' contributions.
     *
     * @return array<string, mixed>
     */
    public function summary(Ship $ship, string $currency = 'USD'): array
    {
        $ship->loadMissing(['ownerships.owner']);

        $totalExpenses = (float) $ship->expenses()
            ->where('currency', $currency)
            ->sum('amount');

        $contributions = $ship->partnerContributions()
            ->where('currency', $currency)
            ->get()
            ->groupBy('owner_id')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2));

        $spender = $ship->ownerships->firstWhere('is_managing', true)
            ?? $ship->ownerships->sortByDesc('share_percent')->first();

        $othersPaid = 0.0;
        $partners = [];

        foreach ($ship->ownerships as $ownership) {
            /** @var ShipOwnership $ownership */
            $isSpender = $spender && (int) $ownership->id === (int) $spender->id;
            $paid = $isSpender
                ? 0.0
                : (float) ($contributions[$ownership->owner_id] ?? 0);
            if (! $isSpender) {
                $othersPaid = round($othersPaid + $paid, 2);
            }

            $partners[] = [
                'ownership_id' => $ownership->id,
                'owner_id' => $ownership->owner_id,
                'owner_name' => $ownership->owner?->name,
                'share_percent' => number_format((float) $ownership->share_percent, 2, '.', ''),
                'is_spender' => $isSpender,
                'paid' => $paid,
            ];
        }

        $spenderImplied = round($totalExpenses - $othersPaid, 2);

        foreach ($partners as &$partner) {
            if ($partner['is_spender']) {
                $partner['paid'] = $spenderImplied;
            }
            $fair = round($totalExpenses * ((float) $partner['share_percent'] / 100), 2);
            $partner['fair_share'] = number_format($fair, 2, '.', '');
            $partner['paid_formatted'] = number_format((float) $partner['paid'], 2, '.', '');
            $partner['variance'] = number_format((float) $partner['paid'] - $fair, 2, '.', '');
        }
        unset($partner);

        $spenderPaid = $spenderImplied;
        $difference = round($spenderPaid - $othersPaid, 2);

        return [
            'currency' => $currency,
            'total_expenses' => number_format($totalExpenses, 2, '.', ''),
            'spender_owner_id' => $spender?->owner_id,
            'spender_name' => $spender?->owner?->name,
            'spender_paid' => number_format($spenderPaid, 2, '.', ''),
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
