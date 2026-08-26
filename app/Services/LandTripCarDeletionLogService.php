<?php

namespace App\Services;

use App\Enums\LandTripCarDeletionSource;
use App\Models\Company;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarDeletion;
use App\Models\LandTripCarDeletionItem;
use App\Models\User;
use App\Support\ApplicationTimezone;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LandTripCarDeletionLogService
{
    /**
     * @param  Collection<int, LandTripCar>  $cars
     */
    public function record(
        Company $company,
        User $actor,
        Collection $cars,
        LandTripCarDeletionSource $source = LandTripCarDeletionSource::Manual
    ): ?LandTripCarDeletion {
        $items = $cars
            ->unique('id')
            ->values()
            ->map(fn (LandTripCar $car) => [
                'land_trip_car_id' => $car->id,
                'chassis_no' => $car->chassis_no,
                'model' => $car->model,
                'cmr_waybill' => $car->cmr_waybill,
            ]);

        if ($items->isEmpty()) {
            return null;
        }

        $deletion = LandTripCarDeletion::query()->create([
            'company_id' => $company->id,
            'user_id' => $actor->id,
            'cars_count' => $items->count(),
            'source' => $source,
        ]);

        $deletion->items()->createMany($items->all());

        Log::info('Land trip cars deleted.', [
            'company_id' => $company->id,
            'deletion_id' => $deletion->id,
            'deleted_by' => $actor->id,
            'source' => $source->value,
            'count' => $deletion->cars_count,
            'car_ids' => $items->pluck('land_trip_car_id')->all(),
            'chassis_nos' => $items->pluck('chassis_no')->all(),
        ]);

        return $deletion;
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function restore(Company $company, User $actor, int $deletionId, array $itemIds = []): int
    {
        $deletion = LandTripCarDeletion::query()
            ->where('company_id', $company->id)
            ->whereKey($deletionId)
            ->first();

        if ($deletion === null) {
            throw ValidationException::withMessages([
                'deletion_id' => 'The deletion log entry was not found for this company.',
            ]);
        }

        $wantedIds = array_values(array_unique(array_map('intval', $itemIds)));

        $items = LandTripCarDeletionItem::query()
            ->where('land_trip_car_deletion_id', $deletion->id)
            ->whereNull('restored_at')
            ->when($wantedIds !== [], fn ($query) => $query->whereIn('id', $wantedIds))
            ->get();

        if ($wantedIds !== [] && $items->count() !== count($wantedIds)) {
            throw ValidationException::withMessages([
                'item_ids' => 'One or more cars are not in this deletion log or were already restored.',
            ]);
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'restore' => 'There are no deleted cars left to restore in this entry.',
            ]);
        }

        return DB::transaction(function () use ($company, $actor, $deletion, $items): int {
            $restored = 0;
            $now = now();

            foreach ($items as $item) {
                if ($this->restoreItem($company, $actor, $item, $now)) {
                    $restored++;
                }
            }

            if ($restored === 0) {
                throw ValidationException::withMessages([
                    'restore' => 'These cars can no longer be restored.',
                ]);
            }

            $pending = LandTripCarDeletionItem::query()
                ->where('land_trip_car_deletion_id', $deletion->id)
                ->whereNull('restored_at')
                ->exists();

            if (! $pending) {
                $deletion->update([
                    'restored_at' => $now,
                    'restored_by' => $actor->id,
                ]);
            }

            Log::info('Land trip cars restored.', [
                'company_id' => $company->id,
                'deletion_id' => $deletion->id,
                'restored_by' => $actor->id,
                'count' => $restored,
                'car_ids' => $items->pluck('land_trip_car_id')->filter()->values()->all(),
            ]);

            return $restored;
        });
    }

    /**
     * @param  array{search?: string|null}  $filters
     */
    public function paginateForCompany(Company $company, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return LandTripCarDeletion::query()
            ->where('company_id', $company->id)
            ->with([
                'user:id,name',
                'restoredByUser:id,name',
                'items.restoredByUser:id,name',
                'items.car' => fn ($query) => $query->withTrashed(),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('items', function ($items) use ($search): void {
                    $items->where(function ($inner) use ($search): void {
                        $inner
                            ->where('chassis_no', 'like', '%'.$search.'%')
                            ->orWhere('cmr_waybill', 'like', '%'.$search.'%')
                            ->orWhere('model', 'like', '%'.$search.'%');
                    });
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (LandTripCarDeletion $deletion) => $this->transform($deletion));
    }

    /**
     * @return array{unrestored_count: int}
     */
    public function meta(Company $company): array
    {
        return [
            'unrestored_count' => (int) LandTripCarDeletionItem::query()
                ->whereNull('restored_at')
                ->whereHas('deletion', fn ($query) => $query->where('company_id', $company->id))
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(LandTripCarDeletion $deletion): array
    {
        $items = $deletion->items
            ->sortBy('id')
            ->values();
        $chassis = $items->pluck('chassis_no')->filter()->values();
        $pending = $items->filter(fn (LandTripCarDeletionItem $item) => ! $item->isRestored());

        return [
            'id' => $deletion->id,
            'created_at' => ApplicationTimezone::formatDateTime($deletion->created_at),
            'user_name' => $deletion->user?->name,
            'cars_count' => $deletion->cars_count,
            'pending_count' => $pending->count(),
            'source' => $deletion->source?->value ?? LandTripCarDeletionSource::Manual->value,
            'restored' => $deletion->isFullyRestored(),
            'restored_at' => ApplicationTimezone::formatDateTime($deletion->restored_at),
            'restored_by_name' => $deletion->restoredByUser?->name,
            'can_restore' => $pending->isNotEmpty(),
            'chassis_preview' => $chassis->take(8)->implode(', '),
            'items' => $items->map(function (LandTripCarDeletionItem $item) {
                $car = $item->car;

                return [
                    'id' => $item->id,
                    'chassis_no' => $item->chassis_no,
                    'model' => $item->model,
                    'cmr_waybill' => $item->cmr_waybill,
                    'restored' => $item->isRestored(),
                    'restored_at' => ApplicationTimezone::formatDateTime($item->restored_at),
                    'restored_by_name' => $item->restoredByUser?->name,
                    'can_restore' => ! $item->isRestored() && $car !== null,
                    'missing' => $car === null,
                ];
            })->all(),
        ];
    }

    private function restoreItem(Company $company, User $actor, LandTripCarDeletionItem $item, CarbonInterface $now): bool
    {
        if ($item->land_trip_car_id === null) {
            return false;
        }

        $car = LandTripCar::withTrashed()
            ->whereKey($item->land_trip_car_id)
            ->whereIn(
                'land_trip_id',
                LandTrip::withTrashed()->where('company_id', $company->id)->select('id')
            )
            ->first();

        if ($car === null) {
            return false;
        }

        if ($car->trashed()) {
            $car->restore();
        }

        $item->update([
            'restored_at' => $now,
            'restored_by' => $actor->id,
        ]);

        return true;
    }
}
