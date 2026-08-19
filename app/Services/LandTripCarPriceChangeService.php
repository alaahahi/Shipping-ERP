<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LandTripCar;
use App\Models\LandTripCarPriceChange;
use App\Models\User;
use App\Support\ApplicationTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LandTripCarPriceChangeService
{
    /**
     * @param  Collection<int, LandTripCar>  $cars
     */
    public function record(Company $company, User $actor, Collection $cars, float $price): ?LandTripCarPriceChange
    {
        $amount = round(max(0, $price), 2);
        $items = $cars
            ->unique('id')
            ->values()
            ->map(function (LandTripCar $car) use ($amount): ?array {
                $oldPrice = round((float) $car->price, 2);
                if ($oldPrice === $amount) {
                    return null;
                }

                return [
                    'land_trip_car_id' => $car->id,
                    'chassis_no' => $car->chassis_no,
                    'old_price' => $oldPrice,
                    'new_price' => $amount,
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        $change = LandTripCarPriceChange::query()->create([
            'company_id' => $company->id,
            'user_id' => $actor->id,
            'batch_uuid' => (string) Str::uuid(),
            'cars_count' => $items->count(),
            'new_price' => $amount,
        ]);

        $change->items()->createMany($items->all());

        Log::info('Land trip car prices updated in bulk.', [
            'company_id' => $company->id,
            'change_id' => $change->id,
            'batch_uuid' => $change->batch_uuid,
            'user_id' => $actor->id,
            'cars_count' => $change->cars_count,
            'car_ids' => $items->pluck('land_trip_car_id')->all(),
            'new_price' => $amount,
        ]);

        return $change;
    }

    public function paginateForCompany(Company $company, int $perPage = 20): LengthAwarePaginator
    {
        return LandTripCarPriceChange::query()
            ->where('company_id', $company->id)
            ->with([
                'user:id,name',
                'items',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (LandTripCarPriceChange $change) => $this->transform($change));
    }

    public function hasEntriesForCompany(Company $company): bool
    {
        return LandTripCarPriceChange::query()
            ->where('company_id', $company->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(LandTripCarPriceChange $change): array
    {
        $chassis = $change->items
            ->pluck('chassis_no')
            ->filter()
            ->values();

        return [
            'id' => $change->id,
            'batch_uuid' => $change->batch_uuid,
            'created_at' => ApplicationTimezone::formatDateTime($change->created_at),
            'user_name' => $change->user?->name,
            'cars_count' => $change->cars_count,
            'new_price' => $change->new_price,
            'chassis_preview' => $chassis->take(8)->implode(', '),
            'items' => $change->items->map(fn ($item) => [
                'id' => $item->id,
                'chassis_no' => $item->chassis_no,
                'old_price' => $item->old_price,
                'new_price' => $item->new_price,
            ])->values()->all(),
        ];
    }
}
