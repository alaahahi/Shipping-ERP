<?php

namespace App\Services;

use App\Enums\VoyageStatus;
use App\Models\Voyage;
use App\Models\VoyageCar;
use App\Models\VoyageExpense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VoyageReportService
{
    public function __construct(
        private readonly VoyageSettlementService $settlementService
    ) {}

    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     ship_id?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Voyage $voyage) => $this->transformVoyageRow($voyage));
    }

    /**
     * Full filtered rows for Excel/PDF export.
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     ship_id?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters = [], int $limit = 2000): array
    {
        return $this->filteredQuery($filters)
            ->limit($limit)
            ->get()
            ->map(fn (Voyage $voyage) => $this->transformVoyageRow($voyage))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfMonth();

        $voyageQuery = Voyage::query()
            ->whereBetween('sailing_date', [$from->toDateString(), $to->toDateString()]);

        $voyageIds = (clone $voyageQuery)->pluck('id');

        $voyages = Voyage::query()
            ->with(['companies', 'cars:id,voyage_id,voyage_company_id', 'expenses:id,voyage_id,amount,currency'])
            ->whereIn('id', $voyageIds)
            ->get();

        $revenueUsd = 0.0;
        $expensesUsd = 0.0;
        $expensesAed = 0.0;
        $carsCount = 0;
        $commissionAed = 0.0;

        foreach ($voyages as $voyage) {
            $settlement = $this->settlementService->forVoyage($voyage);
            $revenueUsd += (float) $settlement['summary']['revenue_usd'];
            $expensesUsd += (float) $settlement['summary']['expenses_usd'];
            $expensesAed += (float) $settlement['summary']['expenses_aed'];
            $carsCount += (int) $settlement['summary']['cars_count'];
            $commissionAed += (float) $settlement['summary']['total_captain_commission_aed'];
        }

        return [
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'voyages_count' => $voyages->count(),
            'voyages_active' => Voyage::query()->where('status', VoyageStatus::Active)->count(),
            'voyages_draft' => Voyage::query()->where('status', VoyageStatus::Draft)->count(),
            'voyages_closed' => Voyage::query()->where('status', VoyageStatus::Closed)->count(),
            'cars_count' => $carsCount,
            'cars_total' => VoyageCar::query()->count(),
            'revenue_usd' => $this->money($revenueUsd),
            'expenses_usd' => $this->money($expensesUsd),
            'expenses_aed' => $this->money($expensesAed),
            'profit_usd' => $this->money($revenueUsd - $expensesUsd),
            'commission_aed' => $this->money($commissionAed),
            'expense_rows' => VoyageExpense::query()->whereIn('voyage_id', $voyageIds)->count(),
        ];
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     ship_id?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Voyage::query()
            ->with([
                'ship:id,name',
                'companies',
                'cars:id,voyage_id,voyage_company_id,consignee_name',
                'expenses:id,voyage_id,amount,currency',
            ])
            ->latest('sailing_date')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('voyage_number', 'like', "%{$search}%")
                    ->orWhere('pol', 'like', "%{$search}%")
                    ->orWhere('pod', 'like', "%{$search}%")
                    ->orWhereHas('ship', fn ($ship) => $ship->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['ship_id'])) {
            $query->where('ship_id', $filters['ship_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('sailing_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('sailing_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformVoyageRow(Voyage $voyage): array
    {
        $settlement = $this->settlementService->forVoyage($voyage);

        return [
            'id' => $voyage->id,
            'voyage_number' => $voyage->voyage_number,
            'sailing_date' => $voyage->sailing_date?->format('Y-m-d'),
            'status' => $voyage->status->value,
            'status_label' => $voyage->status->label(),
            'status_tone' => $voyage->status->tone(),
            'ship_name' => $voyage->ship?->name,
            'route' => trim(($voyage->pol ?? '—').' → '.($voyage->pod ?? '—')),
            'cars_count' => $settlement['summary']['cars_count'],
            'companies_count' => $settlement['summary']['companies_count'],
            'revenue_usd' => $settlement['summary']['revenue_usd'],
            'expenses_usd' => $settlement['summary']['expenses_usd'],
            'profit_usd' => $settlement['summary']['profit_usd'],
            'commission_aed' => $settlement['summary']['total_captain_commission_aed'],
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
