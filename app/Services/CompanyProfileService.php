<?php

namespace App\Services;

use App\Models\Company;
use App\Models\MoneyVoucher;
use App\Models\VoyageCar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyProfileService
{
    public function __construct(
        private readonly MoneyVoucherService $moneyVoucherService
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function receiptVouchers(Company $company): array
    {
        return MoneyVoucher::query()
            ->where('company_id', $company->id)
            ->latest('voucher_date')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (MoneyVoucher $voucher) => $this->moneyVoucherService->transform($voucher))
            ->all();
    }

    public function paginateCars(Company $company, string $search = '', int $perPage = 30): LengthAwarePaginator
    {
        $query = VoyageCar::query()
            ->with([
                'voyage:id,voyage_number,sailing_date,status',
                'car:id,vin,description',
            ])
            ->whereHas('company', fn ($builder) => $builder->where('company_id', $company->id))
            ->latest('id');

        $term = trim($search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($builder) use ($like): void {
                $builder
                    ->where('chassis_no', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('consignee_name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('car', function ($car) use ($like): void {
                        $car->where('vin', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    });
            });
        }

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (VoyageCar $car) => [
                'id' => $car->id,
                'chassis_no' => $car->chassis_no ?: $car->car?->vin,
                'name' => $car->description ?: $car->car?->description,
                'consignee_name' => $car->consignee_name,
                'code' => $car->code,
                'voyage_id' => $car->voyage_id,
                'voyage_number' => $car->voyage?->voyage_number,
                'sailing_date' => $car->voyage?->sailing_date?->format('Y-m-d'),
            ]);
    }
}
