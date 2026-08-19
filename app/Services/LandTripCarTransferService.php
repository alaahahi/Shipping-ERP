<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarCompanyTransfer;
use App\Models\User;
use App\Support\ApplicationTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LandTripCarTransferService
{
    /**
     * @param  Collection<int, LandTripCar>  $cars
     */
    public function record(
        Company $fromCompany,
        Company $toCompany,
        User $actor,
        Collection $cars,
        LandTrip $toTrip,
        ?string $notes = null
    ): ?LandTripCarCompanyTransfer {
        $items = $cars
            ->unique('id')
            ->values()
            ->map(fn (LandTripCar $car): array => [
                'land_trip_car_id' => $car->id,
                'chassis_no' => $car->chassis_no,
                'from_land_trip_id' => $car->land_trip_id,
                'to_land_trip_id' => $toTrip->id,
                'cmr_waybill' => $car->cmr_waybill,
            ]);

        if ($items->isEmpty() || (int) $fromCompany->id === (int) $toCompany->id) {
            return null;
        }

        $transfer = LandTripCarCompanyTransfer::query()->create([
            'from_company_id' => $fromCompany->id,
            'to_company_id' => $toCompany->id,
            'user_id' => $actor->id,
            'cars_count' => $items->count(),
            'notes' => $notes,
        ]);
        $transfer->items()->createMany($items->all());

        Log::info('Land trip cars transferred between companies.', [
            'from_company_id' => $fromCompany->id,
            'to_company_id' => $toCompany->id,
            'transfer_id' => $transfer->id,
            'user_id' => $actor->id,
            'car_ids' => $cars->pluck('id')->all(),
            'chassis_nos' => $cars->pluck('chassis_no')->all(),
            'to_land_trip_id' => $toTrip->id,
        ]);

        return $transfer;
    }

    public function paginateForCompany(Company $company, int $perPage = 20): LengthAwarePaginator
    {
        return LandTripCarCompanyTransfer::query()
            ->where(function ($query) use ($company): void {
                $query->where('from_company_id', $company->id)
                    ->orWhere('to_company_id', $company->id);
            })
            ->with([
                'user:id,name',
                'fromCompany:id,name',
                'toCompany:id,name',
                'items',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (LandTripCarCompanyTransfer $transfer) => $this->transform($transfer, $company));
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(LandTripCarCompanyTransfer $transfer, ?Company $viewer = null): array
    {
        $chassis = $transfer->items->pluck('chassis_no')->filter()->values();
        $direction = 'outgoing';
        if ($viewer && (int) $transfer->to_company_id === (int) $viewer->id) {
            $direction = (int) $transfer->from_company_id === (int) $viewer->id ? 'internal' : 'incoming';
        }

        return [
            'id' => $transfer->id,
            'created_at' => ApplicationTimezone::formatDateTime($transfer->created_at),
            'user_name' => $transfer->user?->name,
            'from_company_id' => $transfer->from_company_id,
            'from_company_name' => $transfer->fromCompany?->name,
            'to_company_id' => $transfer->to_company_id,
            'to_company_name' => $transfer->toCompany?->name,
            'cars_count' => $transfer->cars_count,
            'notes' => $transfer->notes,
            'direction' => $direction,
            'chassis_preview' => $chassis->take(8)->implode(', '),
            'items' => $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'chassis_no' => $item->chassis_no,
                'cmr_waybill' => $item->cmr_waybill,
            ])->values()->all(),
        ];
    }
}
