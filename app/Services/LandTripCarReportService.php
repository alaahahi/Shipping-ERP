<?php

namespace App\Services;

use App\Models\Country;
use App\Models\LandTripCar;
use App\Models\LandTripCarStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LandTripCarReportService
{
    public const PER_PAGE = 100;

    /**
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>}  $filters
     */
    public function paginate(array $filters, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        if (! $this->hasScope($filters)) {
            return LandTripCar::query()
                ->whereRaw('0 = 1')
                ->paginate($perPage)
                ->withQueryString();
        }

        return $this->carsQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>}  $filters
     * @return Collection<int, LandTripCar>
     */
    public function list(array $filters): Collection
    {
        if (! $this->hasScope($filters)) {
            return collect();
        }

        return $this->carsQuery($filters)->get();
    }

    /**
     * @return array{
     *     countries: list<array{id: int, label: string, count: int}>,
     *     locations: list<array{id: int, label: string, country_id: int|null, country_label: string|null, is_archive: bool, count: int}>
     * }
     */
    public function filterOptions(): array
    {
        $counts = LandTripCar::query()
            ->selectRaw('location_status_id, COUNT(*) as aggregate')
            ->groupBy('location_status_id')
            ->pluck('aggregate', 'location_status_id');

        $locations = LandTripCarStatus::query()
            ->with('country:id,name,name_ar,iso_code')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (LandTripCarStatus $status) use ($counts) {
                return [
                    'id' => $status->id,
                    'label' => $status->localizedName(),
                    'country_id' => $status->country_id ? (int) $status->country_id : null,
                    'country_label' => $status->country?->localizedName(),
                    'is_archive' => (bool) $status->is_archive,
                    'count' => (int) ($counts[$status->id] ?? 0),
                ];
            })
            ->all();

        $countryCounts = [];
        foreach ($locations as $location) {
            $countryId = $location['country_id'];
            if ($countryId === null) {
                continue;
            }
            $countryCounts[$countryId] = ($countryCounts[$countryId] ?? 0) + $location['count'];
        }

        $countries = Country::query()
            ->whereIn('id', array_keys($countryCounts))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Country $country) => [
                'id' => $country->id,
                'label' => $country->localizedName(),
                'count' => (int) ($countryCounts[$country->id] ?? 0),
            ])
            ->all();

        return [
            'countries' => $countries,
            'locations' => $locations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transformCar(LandTripCar $car): array
    {
        $status = $car->locationStatus;

        return [
            'id' => $car->id,
            'chassis_no' => $car->chassis_no,
            'cmr_waybill' => $car->cmr_waybill,
            'consignee_name' => $car->consignee_name,
            'model' => $car->model ?: $car->description,
            'color' => $car->color,
            'year' => $car->year,
            'weight' => $car->weight !== null ? (string) $car->weight : null,
            'price' => number_format((float) $car->price, 2, '.', ''),
            'notes' => $car->notes,
            'company_id' => $car->landTrip?->company_id,
            'company_name' => $car->landTrip?->company?->name,
            'country_label' => $status?->country?->localizedName(),
            'location_status_id' => $car->location_status_id,
            'location_status_label' => $status?->localizedName(),
            'location_status_color' => $status?->resolvedColor(),
            'created_at_label' => optional($car->created_at)?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>}  $filters
     */
    public function hasScope(array $filters): bool
    {
        return $this->idList($filters['country_ids'] ?? []) !== []
            || $this->idList($filters['location_status_ids'] ?? []) !== [];
    }

    /**
     * @param  array{country_ids?: list<int>, location_status_ids?: list<int>}  $filters
     */
    private function carsQuery(array $filters): Builder
    {
        $countryIds = $this->idList($filters['country_ids'] ?? []);
        $locationIds = $this->idList($filters['location_status_ids'] ?? []);

        $query = LandTripCar::query()
            ->select('land_trip_cars.*')
            ->with([
                'locationStatus.country:id,name,name_ar,iso_code',
                'landTrip:id,company_id',
                'landTrip.company:id,name',
            ])
            ->whereHas('landTrip');

        if ($locationIds !== []) {
            $query->whereIn('land_trip_cars.location_status_id', $locationIds);
        }

        if ($countryIds !== []) {
            $query->whereHas('locationStatus', function (Builder $builder) use ($countryIds): void {
                $builder->whereIn('country_id', $countryIds);
            });
        }

        return $query
            ->orderByDesc('land_trip_cars.created_at')
            ->orderByDesc('land_trip_cars.id');
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function idList(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }
}
