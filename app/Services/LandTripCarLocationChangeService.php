<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LandTripCar;
use App\Models\LandTripCarLocationChange;
use App\Models\LandTripCarStatus;
use App\Models\User;
use App\Support\ApplicationTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LandTripCarLocationChangeService
{
    /**
     * @param  Collection<int, LandTripCar>  $cars
     */
    public function record(Company $company, User $actor, Collection $cars, ?int $toStatusId): ?LandTripCarLocationChange
    {
        $items = $cars
            ->unique('id')
            ->values()
            ->map(function (LandTripCar $car) use ($toStatusId): ?array {
                $from = $car->location_status_id !== null ? (int) $car->location_status_id : null;
                if ($from === $toStatusId) {
                    return null;
                }

                return [
                    'land_trip_car_id' => $car->id,
                    'from_location_status_id' => $from,
                    'to_location_status_id' => $toStatusId,
                    'chassis_no' => $car->chassis_no,
                ];
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        $change = LandTripCarLocationChange::query()->create([
            'company_id' => $company->id,
            'user_id' => $actor->id,
            'to_location_status_id' => $toStatusId,
            'cars_count' => $items->count(),
        ]);

        $change->items()->createMany($items->all());

        Log::info('Land trip car locations updated.', [
            'company_id' => $company->id,
            'change_id' => $change->id,
            'user_id' => $actor->id,
            'to_location_status_id' => $toStatusId,
            'cars_count' => $change->cars_count,
        ]);

        return $change;
    }

    public function canUndo(Company $company): bool
    {
        return $this->latestApplied($company) !== null;
    }

    public function undoLatest(Company $company, User $actor): LandTripCarLocationChange
    {
        $change = $this->latestApplied($company);

        if ($change === null) {
            throw ValidationException::withMessages([
                'undo' => 'There is no location change to undo.',
            ]);
        }

        return DB::transaction(function () use ($company, $actor, $change): LandTripCarLocationChange {
            $change->loadMissing('items');

            foreach ($change->items as $item) {
                if ($item->land_trip_car_id === null) {
                    continue;
                }

                LandTripCar::query()
                    ->where('id', $item->land_trip_car_id)
                    ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
                    ->update(['location_status_id' => $item->from_location_status_id]);
            }

            $change->update([
                'undone_at' => now(),
                'undone_by' => $actor->id,
            ]);

            Log::info('Land trip car location change undone.', [
                'company_id' => $company->id,
                'change_id' => $change->id,
                'user_id' => $actor->id,
                'cars_count' => $change->cars_count,
            ]);

            return $change->fresh(['user', 'undoneByUser', 'toLocationStatus', 'items.fromLocationStatus', 'items.toLocationStatus']);
        });
    }

    public function paginateForCompany(Company $company, int $perPage = 20): LengthAwarePaginator
    {
        $latestId = $this->latestApplied($company)?->id;

        return LandTripCarLocationChange::query()
            ->where('company_id', $company->id)
            ->with([
                'user:id,name',
                'undoneByUser:id,name',
                'toLocationStatus:id,code,name,name_ar,name_ckb,row_tone,color',
                'items.fromLocationStatus:id,code,name,name_ar,name_ckb,row_tone,color',
                'items.toLocationStatus:id,code,name,name_ar,name_ckb,row_tone,color',
            ])
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (LandTripCarLocationChange $change) => $this->transform($change, $latestId));
    }

    /**
     * @return array{can_undo: bool}
     */
    public function meta(Company $company): array
    {
        return [
            'can_undo' => $this->canUndo($company),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(LandTripCarLocationChange $change, ?int $latestUndoableId = null): array
    {
        $chassis = $change->items
            ->pluck('chassis_no')
            ->filter()
            ->values();

        return [
            'id' => $change->id,
            'created_at' => ApplicationTimezone::formatDateTime($change->created_at),
            'user_name' => $change->user?->name,
            'to_location_label' => $this->statusLabel($change->toLocationStatus),
            'cars_count' => $change->cars_count,
            'chassis_preview' => $chassis->take(8)->implode(', '),
            'undone' => $change->isUndone(),
            'undone_at' => ApplicationTimezone::formatDateTime($change->undone_at),
            'undone_by_name' => $change->undoneByUser?->name,
            'can_undo' => $latestUndoableId !== null && (int) $change->id === (int) $latestUndoableId,
            'items' => $change->items->map(fn ($item) => [
                'id' => $item->id,
                'chassis_no' => $item->chassis_no,
                'from_location_label' => $this->statusLabel($item->fromLocationStatus),
                'to_location_label' => $this->statusLabel($item->toLocationStatus),
            ])->values()->all(),
        ];
    }

    private function latestApplied(Company $company): ?LandTripCarLocationChange
    {
        return LandTripCarLocationChange::query()
            ->where('company_id', $company->id)
            ->whereNull('undone_at')
            ->latest('id')
            ->first();
    }

    private function statusLabel(?LandTripCarStatus $status): string
    {
        return $status?->localizedName() ?: '';
    }
}
