<?php

namespace App\Services;

use App\Models\Voyage;
use Illuminate\Support\Collection;

class VoyageSettlementService
{
    /**
     * Operational dues only — never stored balances.
     *
     * @return array{
     *     companies: list<array<string, mixed>>,
     *     consignees: list<array<string, mixed>>,
     *     summary: array<string, mixed>
     * }
     */
    public function forVoyage(Voyage $voyage): array
    {
        $voyage->loadMissing(['companies.company', 'cars', 'expenses', 'ship.ownerships.owner']);

        $cars = $voyage->cars;
        $companies = $voyage->companies->keyBy('id');

        $companyRows = $companies->map(function ($company) use ($cars): array {
            $companyCars = $cars->where('voyage_company_id', $company->id);
            $count = $companyCars->count();
            $shippingUsd = $count * (float) $company->shipping_price_per_car;
            $clearanceUsd = $count * (float) $company->clearance_per_car;
            $shippingAed = $count * (float) $company->shipping_price_aed;

            return [
                'company_id' => $company->id,
                'master_company_id' => $company->company_id,
                'company_name' => $company->company?->name ?? $company->company_name,
                'cars_count' => $count,
                'shipping_price_per_car' => $this->money((float) $company->shipping_price_per_car),
                'clearance_per_car' => $this->money((float) $company->clearance_per_car),
                'shipping_price_aed' => $this->money((float) $company->shipping_price_aed),
                'shipping_total_usd' => $this->money($shippingUsd),
                'clearance_total_usd' => $this->money($clearanceUsd),
                'shipping_total_aed' => $this->money($shippingAed),
                'due_usd' => $this->money($shippingUsd + $clearanceUsd),
            ];
        })->values()->all();

        $consignees = $cars
            ->groupBy(fn ($car) => mb_strtolower(trim((string) $car->consignee_name)))
            ->map(function (Collection $group) use ($companies): array {
                $first = $group->first();
                $companyIds = $group->pluck('voyage_company_id')->unique()->filter();

                $dueUsd = $group->sum(function ($car) use ($companies): float {
                    $company = $companies->get($car->voyage_company_id);
                    if (! $company) {
                        return 0.0;
                    }

                    return (float) $company->shipping_price_per_car + (float) $company->clearance_per_car;
                });

                return [
                    'consignee_name' => $first?->consignee_name ?? '—',
                    'cars_count' => $group->count(),
                    'companies' => $companyIds
                        ->map(fn ($id) => $companies->get($id)?->company?->name
                            ?? $companies->get($id)?->company_name)
                        ->filter()
                        ->values()
                        ->all(),
                    'due_usd' => $this->money($dueUsd),
                ];
            })
            ->sortByDesc('cars_count')
            ->values()
            ->all();

        $carsCount = $cars->count();
        $revenueUsd = collect($companyRows)->sum(fn (array $row) => (float) $row['due_usd']);
        $expenseUsd = (float) $voyage->expenses
            ->where('currency', 'USD')
            ->sum(fn ($expense) => (float) $expense->amount);
        $expenseAed = (float) $voyage->expenses
            ->where('currency', 'AED')
            ->sum(fn ($expense) => (float) $expense->amount);

        $profitUsd = $revenueUsd - $expenseUsd;
        $ownerships = $voyage->ship?->ownerships ?? collect();
        $ownershipTotalShare = (float) $ownerships->sum('share_percent');
        $ownerRows = $ownerships
            ->sortByDesc('share_percent')
            ->values()
            ->map(function ($ownership) use ($profitUsd): array {
                $share = (float) $ownership->share_percent;
                $shareOfProfit = $profitUsd * ($share / 100);

                return [
                    'ownership_id' => $ownership->id,
                    'owner_id' => $ownership->owner_id,
                    'owner_name' => $ownership->owner?->name ?? '—',
                    'share_percent' => $this->money($share),
                    'is_managing' => (bool) $ownership->is_managing,
                    'share_of_profit_usd' => $this->money($shareOfProfit),
                ];
            })
            ->all();

        $allocatedUsd = collect($ownerRows)->sum(fn (array $row) => (float) $row['share_of_profit_usd']);
        $unallocatedUsd = $profitUsd - $allocatedUsd;

        $costPerCarAed = (float) $voyage->cost_per_car_aed;
        $commissionPerCarAed = (float) $voyage->captain_commission_aed;
        $totalCostAed = $carsCount * $costPerCarAed;
        $totalCommissionAed = $carsCount * $commissionPerCarAed;

        return [
            'companies' => $companyRows,
            'consignees' => $consignees,
            'owners' => $ownerRows,
            'summary' => [
                'cars_count' => $carsCount,
                'companies_count' => $companies->count(),
                'consignees_count' => count($consignees),
                'owners_count' => count($ownerRows),
                'revenue_usd' => $this->money($revenueUsd),
                'expenses_usd' => $this->money($expenseUsd),
                'expenses_aed' => $this->money($expenseAed),
                'profit_usd' => $this->money($profitUsd),
                'ownership_total_share' => $this->money($ownershipTotalShare),
                'ownership_is_complete' => abs($ownershipTotalShare - 100) < 0.01,
                'ownership_allocated_usd' => $this->money($allocatedUsd),
                'ownership_unallocated_usd' => $this->money($unallocatedUsd),
                'cost_per_car_aed' => $this->money($costPerCarAed),
                'captain_commission_per_car_aed' => $this->money($commissionPerCarAed),
                'total_ship_cost_aed' => $this->money($totalCostAed),
                'total_captain_commission_aed' => $this->money($totalCommissionAed),
                'net_ops_aed' => $this->money($totalCostAed - $totalCommissionAed),
                'ship_id' => $voyage->ship_id,
                'ship_name' => $voyage->ship?->name,
            ],
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
