<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\LandTripStatus;
use App\Models\Car;
use App\Models\Company;
use App\Models\Country;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VoyageCar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LandTripService
{
    public const COMPANY_CARS_PER_PAGE = 50;

    public function __construct(
        private readonly LandTripCarStatusService $carStatusService,
        private readonly LandTripCarLocationChangeService $locationChangeService
    ) {}

    /**
     * @param  array{search?: string|null}  $filters
     */
    public function paginateCompanies(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $archiveIds = $this->carStatusService->archiveStatusIds();

        $query = Company::query()
            ->select('companies.*')
            ->where('is_active', true)
            ->withCount('landTrips')
            ->addSelect([
                'cars_count' => LandTripCar::query()
                    ->selectRaw('count(*)')
                    ->whereIn(
                        'land_trip_id',
                        LandTrip::query()
                            ->select('id')
                            ->whereColumn('land_trips.company_id', 'companies.id')
                    )
                    ->when($archiveIds !== [], function ($builder) use ($archiveIds): void {
                        $builder->where(function ($inner) use ($archiveIds): void {
                            $inner
                                ->whereNull('location_status_id')
                                ->orWhereNotIn('location_status_id', $archiveIds);
                        });
                    }),
                'last_departure_date' => LandTrip::query()
                    ->select('departure_date')
                    ->whereColumn('land_trips.company_id', 'companies.id')
                    ->latest('departure_date')
                    ->latest('id')
                    ->limit(1),
            ])
            ->orderByDesc('land_trips_count')
            ->orderBy('name');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhereExists(function ($exists) use ($search): void {
                        $exists->selectRaw('1')
                            ->from('land_trip_cars')
                            ->join('land_trips', 'land_trips.id', '=', 'land_trip_cars.land_trip_id')
                            ->whereColumn('land_trips.company_id', 'companies.id')
                            ->where('land_trip_cars.chassis_no', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginateForCompany(Company $company, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LandTrip::query()
            ->where('company_id', $company->id)
            ->with([
                'fromCountry:id,name,name_ar',
                'toCountry:id,name,name_ar',
                'company:id,name',
                'voyage:id,voyage_number',
                'journalEntry:id,voucher_number,status',
            ])
            ->withCount('cars')
            ->latest('departure_date')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('cmr_number', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function transformCompanyHub(Company $company): array
    {
        $tripsCount = (int) ($company->land_trips_count ?? $company->landTrips()->count());
        $carsCount = array_key_exists('cars_count', $company->getAttributes())
            ? (int) ($company->cars_count ?? 0)
            : (int) tap(
                LandTripCar::query()->whereIn('land_trip_id', $company->landTrips()->select('id')),
                fn ($query) => $this->excludeArchivedCars($query)
            )->count();

        $lastDeparture = $company->last_departure_date
            ?? $company->landTrips()->latest('departure_date')->latest('id')->value('departure_date');

        if ($lastDeparture instanceof \DateTimeInterface) {
            $lastDeparture = $lastDeparture->format('Y-m-d');
        } elseif (is_string($lastDeparture) && $lastDeparture !== '') {
            $lastDeparture = substr($lastDeparture, 0, 10);
        } else {
            $lastDeparture = null;
        }

        $palette = ['#0F766E', '#1D4ED8', '#C2410C', '#BE185D', '#6D28D9', '#0E7490'];
        $dominantTone = 'neutral';
        $dominantColor = $palette[((int) $company->id) % count($palette)];
        if ($carsCount > 0) {
            $top = collect($this->companyStatusSummary($company))
                ->reject(fn ($item) => $item['is_archive'] ?? false)
                ->sortByDesc('count')
                ->first();
            if (is_array($top) && ($top['count'] ?? 0) > 0) {
                $dominantTone = (string) ($top['row_tone'] ?? 'neutral');
                $dominantColor = (string) ($top['color'] ?? $dominantColor);
            }
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
            'contact_name' => $company->contact_name,
            'contact_phone' => $company->contact_phone,
            'trips_count' => $tripsCount,
            'cars_count' => $carsCount,
            'last_departure_date' => $lastDeparture,
            'card_tone' => $dominantTone,
            'card_color' => $dominantColor,
            'card_hue' => ((int) $company->id % 6) + 1,
            'matched_car' => $company->matched_car ?? null,
        ];
    }

    /**
     * @param  list<int>  $companyIds
     * @return array<int, array{id: int, chassis_no: string|null}>
     */
    public function matchedCarsByChassis(array $companyIds, ?string $search): array
    {
        $search = trim((string) $search);
        if ($search === '' || $companyIds === []) {
            return [];
        }

        $cars = LandTripCar::query()
            ->select('land_trip_cars.id', 'land_trip_cars.chassis_no', 'land_trips.company_id')
            ->join('land_trips', 'land_trips.id', '=', 'land_trip_cars.land_trip_id')
            ->whereIn('land_trips.company_id', $companyIds)
            ->where('land_trip_cars.chassis_no', 'like', "%{$search}%")
            ->orderByRaw('CASE WHEN land_trip_cars.chassis_no = ? THEN 0 ELSE 1 END', [$search])
            ->orderBy('land_trip_cars.id')
            ->get();

        $matches = [];
        foreach ($cars as $car) {
            $companyId = (int) $car->company_id;
            if (isset($matches[$companyId])) {
                continue;
            }
            $matches[$companyId] = [
                'id' => (int) $car->id,
                'chassis_no' => $car->chassis_no,
            ];
        }

        return $matches;
    }

    public function workingTripForCompany(Company $company, User $actor): LandTrip
    {
        $existing = LandTrip::query()
            ->where('company_id', $company->id)
            ->whereNull('journal_entry_id')
            ->where('status', '!=', LandTripStatus::Closed->value)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        [$fromId, $toId] = $this->defaultCountryPair();

        return LandTrip::query()->create([
            'cmr_number' => $this->generateAutoCmr($company),
            'driver_name' => null,
            'from_country_id' => $fromId,
            'to_country_id' => $toId,
            'departure_date' => now()->toDateString(),
            'arrival_date' => null,
            'company_id' => $company->id,
            'freight_amount' => 0,
            'currency' => Currency::USD->value,
            'status' => LandTripStatus::Draft->value,
            'voyage_id' => null,
            'notes' => null,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  array{search?: string|null, location_status_id?: string|null, highlight_car_id?: int|null, sort?: string|null}  $filters
     */
    public function paginateCompanyCars(Company $company, array $filters = [], int $perPage = self::COMPANY_CARS_PER_PAGE, ?int $page = null): LengthAwarePaginator
    {
        $query = $this->companyCarsQuery($company, $filters);

        $highlightId = (int) ($filters['highlight_car_id'] ?? 0);
        if ($highlightId > 0) {
            $query->orderByRaw('CASE WHEN land_trip_cars.id = ? THEN 0 ELSE 1 END', [$highlightId]);
        }

        $this->applyCompanyCarSort($query, $filters);

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  array{search?: string|null, location_status_id?: string|null, sort?: string|null}  $filters
     * @return Collection<int, LandTripCar>
     */
    public function listCompanyCarsForExport(Company $company, array $filters = []): Collection
    {
        $query = $this->companyCarsQuery($company, $filters);
        $this->applyCompanyCarSort($query, $filters);

        return $query->get();
    }

    /**
     * @param  array{search?: string|null, location_status_id?: string|null}  $filters
     */
    private function companyCarsQuery(Company $company, array $filters = []): Builder
    {
        $query = LandTripCar::query()
            ->select('land_trip_cars.*')
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
            ->with([
                'locationStatus:id,code,name,name_ar,name_ckb,row_tone,color',
                'landTrip:id,company_id,cmr_number,driver_name',
            ]);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('land_trip_cars.chassis_no', 'like', "%{$search}%")
                    ->orWhere('land_trip_cars.consignee_name', 'like', "%{$search}%")
                    ->orWhere('land_trip_cars.description', 'like', "%{$search}%")
                    ->orWhere('land_trip_cars.cmr_waybill', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['location_status_id'])) {
            $query->where('land_trip_cars.location_status_id', $filters['location_status_id']);
        } else {
            $this->excludeArchivedCars($query);
        }

        return $query;
    }

    public function normalizeCompanyCarSort(?string $sort): string
    {
        return in_array($sort, ['location', 'oldest', 'sequence', 'newest'], true) ? $sort : 'newest';
    }

    /**
     * @param  array{sort?: string|null}  $filters
     */
    private function applyCompanyCarSort(Builder $query, array $filters): void
    {
        $sort = $this->normalizeCompanyCarSort($filters['sort'] ?? null);

        if ($sort === 'location') {
            $query
                ->leftJoin('land_trip_car_statuses as car_location_sort', 'car_location_sort.id', '=', 'land_trip_cars.location_status_id')
                ->orderByRaw('CASE WHEN land_trip_cars.location_status_id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('car_location_sort.sort_order')
                ->orderBy('land_trip_cars.created_at')
                ->orderBy('land_trip_cars.id');

            return;
        }

        if ($sort === 'oldest') {
            $query
                ->orderBy('land_trip_cars.created_at')
                ->orderBy('land_trip_cars.id');

            return;
        }

        if ($sort === 'sequence') {
            $query
                ->orderBy('land_trip_cars.sort_order')
                ->orderByDesc('land_trip_cars.id');

            return;
        }

        $query
            ->orderByDesc('land_trip_cars.created_at')
            ->orderByDesc('land_trip_cars.id');
    }

    /**
     * @param  array{search?: string|null, location_status_id?: string|null, highlight_car_id?: int|null}  $filters
     * @return array{search?: string|null, location_status_id?: string|null, highlight_car_id?: int|null}
     */
    public function resolveCompanyCarFilters(Company $company, array $filters): array
    {
        $highlightId = (int) ($filters['highlight_car_id'] ?? 0);
        if ($highlightId <= 0 || filled($filters['location_status_id'] ?? null)) {
            return $filters;
        }

        $car = LandTripCar::query()
            ->whereKey($highlightId)
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
            ->first();

        $archiveIds = $this->carStatusService->archiveStatusIds();
        if ($car && $car->location_status_id && in_array((int) $car->location_status_id, $archiveIds, true)) {
            $filters['location_status_id'] = (string) $car->location_status_id;
        }

        return $filters;
    }

    private function excludeArchivedCars(Builder $query): void
    {
        $archiveIds = $this->carStatusService->archiveStatusIds();
        if ($archiveIds === []) {
            return;
        }

        $query->where(function (Builder $builder) use ($archiveIds): void {
            $builder
                ->whereNull('land_trip_cars.location_status_id')
                ->orWhereNotIn('land_trip_cars.location_status_id', $archiveIds);
        });
    }

    /**
     * @return list<array{id: int|null, code: string|null, label: string, row_tone: string, color: string, count: int, country_id?: int|null, country_label?: string|null, country_iso?: string|null, latitude?: float|null, longitude?: float|null}>
     */
    public function companyStatusSummary(Company $company): array
    {
        $base = LandTripCar::query()
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id));

        $counts = (clone $base)
            ->selectRaw('location_status_id, COUNT(*) as aggregate')
            ->groupBy('location_status_id')
            ->pluck('aggregate', 'location_status_id');

        $summary = [];
        $assigned = 0;

        foreach ($this->carStatusService->allActive() as $status) {
            $count = (int) ($counts[$status->id] ?? 0);
            $assigned += $count;
            $summary[] = [
                'id' => $status->id,
                'code' => $status->code,
                'label' => $status->localizedName(),
                'row_tone' => $status->row_tone->value,
                'color' => $status->resolvedColor(),
                'is_archive' => (bool) $status->is_archive,
                'count' => $count,
                'country_id' => $status->country_id,
                'country_label' => $status->country?->localizedName(),
                'country_iso' => $status->country?->iso_code,
                'latitude' => $status->country?->latitude,
                'longitude' => $status->country?->longitude,
            ];
        }

        $total = (int) (clone $base)->count();
        $unset = max(0, $total - $assigned);
        if ($unset > 0) {
            $summary[] = [
                'id' => null,
                'code' => null,
                'label' => '—',
                'row_tone' => 'neutral',
                'color' => '#64748B',
                'is_archive' => false,
                'count' => $unset,
            ];
        }

        return $summary;
    }

    /**
     * @param  array{
     *     location_status_id: int,
     *     scope: string,
     *     car_ids?: list<int>|null,
     *     search?: string|null,
     *     location_status_filter_id?: int|null
     * }  $data
     */
    public function bulkUpdateCompanyCarLocations(Company $company, array $data, User $actor): int
    {
        $statusId = (int) $data['location_status_id'];
        $scope = (string) $data['scope'];

        if ($scope !== 'selected') {
            throw ValidationException::withMessages([
                'scope' => 'Only selected cars can be moved.',
            ]);
        }

        $ids = array_values(array_unique(array_map('intval', $data['car_ids'] ?? [])));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'car_ids' => 'Select at least one car.',
            ]);
        }

        $cars = LandTripCar::query()
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
            ->whereIn('id', $ids)
            ->get();

        if ($cars->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($company, $actor, $cars, $statusId): int {
            $change = $this->locationChangeService->record($company, $actor, $cars, $statusId);
            if ($change === null) {
                return 0;
            }

            $updated = LandTripCar::query()
                ->whereIn('id', $change->items()->pluck('land_trip_car_id'))
                ->update(['location_status_id' => $statusId]);

            return $updated;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, updated: int, skipped: int, created_ids: list<int>}
     */
    public function upsertCompanyImportedCars(?Company $company, LandTrip $trip, array $rows): array
    {
        if ($company === null || (int) $trip->company_id !== (int) $company->id) {
            throw ValidationException::withMessages([
                'file' => 'Imported cars must belong to the selected company.',
            ]);
        }

        $this->assertEditable($trip);

        $existing = LandTripCar::query()
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
            ->get()
            ->keyBy(fn (LandTripCar $car) => strtoupper((string) $car->chassis_no));

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $createdIds = [];
        $seen = [];

        foreach ($rows as $row) {
            if (($row['status'] ?? '') !== 'ready') {
                $skipped++;

                continue;
            }

            $chassis = $this->normalizeChassis($row['chassis_no'] ?? null);
            if ($chassis === null || isset($seen[$chassis])) {
                $skipped++;

                continue;
            }
            $seen[$chassis] = true;

            $payload = [
                'chassis_no' => $chassis,
                'cmr_waybill' => $this->nullableString($row['cmr_waybill'] ?? null),
                'consignee_name' => trim((string) ($row['consignee_name'] ?? '')) ?: $company->name,
                'description' => $this->nullableString($row['description'] ?? null),
                'location_status_id' => ! empty($row['location_status_id']) ? (int) $row['location_status_id'] : null,
                'sort_order' => (int) ($row['row_number'] ?? 0),
            ];

            if ($existing->has($chassis)) {
                $car = $existing->get($chassis);
                if (empty($payload['location_status_id'])) {
                    $payload['location_status_id'] = $car->location_status_id;
                }
                $car->update($payload);
                $updated++;

                continue;
            }

            $elsewhere = LandTripCar::query()
                ->where('chassis_no', $chassis)
                ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', '!=', $company->id))
                ->exists();

            if ($elsewhere) {
                $skipped++;

                continue;
            }

            $payload['car_id'] = $this->findOrCreateCar($chassis, $payload['description'])->id;
            $created = $trip->cars()->create($payload);
            $createdIds[] = (int) $created->id;
            $imported++;
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'created_ids' => $createdIds,
        ];
    }

    /**
     * @param  list<int>  $carIds
     */
    public function deleteCompanyCars(Company $company, array $carIds, User $actor): int
    {
        $ids = array_values(array_unique(array_map('intval', $carIds)));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'car_ids' => 'Select at least one car.',
            ]);
        }

        return DB::transaction(function () use ($company, $ids, $actor): int {
            $cars = LandTripCar::query()
                ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
                ->whereIn('id', $ids)
                ->get();

            if ($cars->isEmpty()) {
                return 0;
            }

            Log::info('Land trip cars deleted.', [
                'company_id' => $company->id,
                'deleted_by' => $actor->id,
                'count' => $cars->count(),
                'car_ids' => $cars->pluck('id')->all(),
                'chassis_nos' => $cars->pluck('chassis_no')->all(),
            ]);

            LandTripCar::query()
                ->whereIn('id', $cars->pluck('id'))
                ->delete();

            return $cars->count();
        });
    }

    public function updateCompanyCarPrice(Company $company, LandTripCar $car, float $price, User $actor): LandTripCar
    {
        $car->loadMissing('landTrip');

        if ((int) $car->landTrip?->company_id !== (int) $company->id) {
            throw ValidationException::withMessages([
                'car' => 'This car does not belong to the selected company.',
            ]);
        }

        $amount = round(max(0, $price), 2);
        $car->update(['price' => $amount]);

        Log::info('Land trip car price updated.', [
            'company_id' => $company->id,
            'car_id' => $car->id,
            'price' => $amount,
            'user_id' => $actor->id,
        ]);

        return $car->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCompanyCar(Company $company, LandTripCar $car, array $data, User $actor): LandTripCar
    {
        $car->loadMissing('landTrip');

        if ((int) $car->landTrip?->company_id !== (int) $company->id) {
            throw ValidationException::withMessages([
                'car' => 'This car does not belong to the selected company.',
            ]);
        }

        if (! $car->landTrip?->isEditable()) {
            $targetCompanyId = (int) ($data['company_id'] ?? $company->id);
            if ($targetCompanyId !== (int) $company->id) {
                throw ValidationException::withMessages([
                    'car' => 'Cannot edit cars on a posted or closed manifest.',
                ]);
            }

            $newStatusId = filled($data['location_status_id'] ?? null)
                ? (int) $data['location_status_id']
                : null;

            return DB::transaction(function () use ($company, $actor, $car, $newStatusId): LandTripCar {
                $this->locationChangeService->record($company, $actor, collect([$car]), $newStatusId);

                $car->update([
                    'location_status_id' => $newStatusId,
                ]);

                return $car->refresh()->load('locationStatus');
            });
        }

        $targetCompanyId = (int) ($data['company_id'] ?? $company->id);

        return DB::transaction(function () use ($company, $car, $data, $actor, $targetCompanyId): LandTripCar {
            $targetCompany = $company;
            $targetTripId = (int) $car->land_trip_id;

            if ($targetCompanyId !== (int) $company->id) {
                $targetCompany = Company::query()->findOrFail($targetCompanyId);
                $targetTrip = $this->workingTripForCompany($targetCompany, $actor);
                $this->assertEditable($targetTrip);
                $targetTripId = (int) $targetTrip->id;
            }

            $seenChassis = [];
            $payload = $this->normalizeCompanyCarRow(
                [
                    ...$data,
                    'voyage_car_id' => $car->voyage_car_id,
                    'sort_order' => $car->sort_order,
                ],
                0,
                $targetCompany,
                $seenChassis
            );

            if ($payload === null) {
                throw ValidationException::withMessages([
                    'chassis_no' => 'Enter a chassis number or description.',
                ]);
            }

            $chassis = $payload['chassis_no'] ?? null;
            if ($chassis) {
                $duplicate = LandTripCar::query()
                    ->where('chassis_no', $chassis)
                    ->where('id', '!=', $car->id)
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'chassis_no' => 'This chassis number is already used.',
                    ]);
                }
            }

            $newStatusId = array_key_exists('location_status_id', $payload)
                ? ($payload['location_status_id'] !== null ? (int) $payload['location_status_id'] : null)
                : ($car->location_status_id !== null ? (int) $car->location_status_id : null);

            $this->locationChangeService->record($targetCompany, $actor, collect([$car]), $newStatusId);

            $car->update([
                ...$payload,
                'land_trip_id' => $targetTripId,
            ]);

            return $car->fresh();
        });
    }

    /**
     * @param  array{cmr_number?: string|null, driver_name?: string|null}  $data
     */
    public function updateCompanyManifestMeta(Company $company, array $data, User $actor): LandTrip
    {
        $trip = $this->workingTripForCompany($company, $actor);
        $this->assertEditable($trip);

        $cmr = $this->nullableString($data['cmr_number'] ?? null);
        if ($cmr !== null) {
            $duplicate = LandTrip::query()
                ->where('cmr_number', $cmr)
                ->where('id', '!=', $trip->id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'cmr_number' => 'This CMR number is already used.',
                ]);
            }
        }

        $trip->update([
            'cmr_number' => $cmr,
            'driver_name' => $this->nullableString($data['driver_name'] ?? null),
        ]);

        return $trip->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function addCompanyCars(Company $company, array $rows, User $actor): int
    {
        return DB::transaction(function () use ($company, $rows, $actor): int {
            $working = $this->workingTripForCompany($company, $actor);
            $this->assertEditable($working);

            $seenChassis = $this->occupiedChassisSet();

            $sortOrder = (int) LandTripCar::query()
                ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
                ->max('sort_order');

            $created = 0;
            foreach ($rows as $index => $row) {
                unset($row['id']);
                $payload = $this->normalizeCompanyCarRow($row, $index, $company, $seenChassis);
                if ($payload === null) {
                    continue;
                }

                $sortOrder += 10;
                $payload['sort_order'] = $sortOrder;
                $working->cars()->create($payload);
                $created++;
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $seenChassis
     * @return array<string, mixed>|null
     */
    private function normalizeCompanyCarRow(array $row, int $index, Company $company, array &$seenChassis): ?array
    {
        $chassis = $this->normalizeChassis($row['chassis_no'] ?? null);
        $consignee = trim((string) ($row['consignee_name'] ?? ''));
        if ($consignee === '') {
            $consignee = $company->name;
        }

        if ($chassis === null && empty($row['voyage_car_id']) && trim((string) ($row['description'] ?? '')) === '') {
            return null;
        }

        if ($chassis !== null) {
            if (isset($seenChassis[$chassis])) {
                throw ValidationException::withMessages([
                    "cars.{$index}.chassis_no" => 'This chassis number is already used.',
                ]);
            }
            $seenChassis[$chassis] = true;
        }

        $voyageCarId = ! empty($row['voyage_car_id']) ? (int) $row['voyage_car_id'] : null;
        $voyageCar = $voyageCarId ? VoyageCar::query()->find($voyageCarId) : null;
        if ($voyageCarId && ! $voyageCar) {
            throw ValidationException::withMessages([
                "cars.{$index}.voyage_car_id" => 'Selected voyage car was not found.',
            ]);
        }

        if ($voyageCar) {
            $chassis = $chassis ?: $this->normalizeChassis($voyageCar->chassis_no);
        }

        $carId = $chassis ? $this->findOrCreateCar($chassis, $row['description'] ?? $voyageCar?->description)->id : null;

        return [
            'voyage_car_id' => $voyageCarId,
            'car_id' => $carId,
            'chassis_no' => $chassis,
            'cmr_waybill' => $this->nullableString($row['cmr_waybill'] ?? null),
            'consignee_name' => $consignee,
            'description' => $this->nullableString($row['description'] ?? $voyageCar?->description),
            'weight' => $row['weight'] ?? $voyageCar?->weight,
            'price' => round((float) ($row['price'] ?? 0), 2),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'location_status_id' => ! empty($row['location_status_id']) ? (int) $row['location_status_id'] : null,
            'sort_order' => (int) ($row['sort_order'] ?? ($index + 1) * 10),
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function defaultCountryPair(): array
    {
        $ids = Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->all();

        if (count($ids) < 2) {
            $ids = Country::query()->orderBy('id')->limit(2)->pluck('id')->all();
        }

        if (count($ids) < 2) {
            throw ValidationException::withMessages([
                'company' => 'Add at least two countries in settings before managing land cars.',
            ]);
        }

        return [(int) $ids[0], (int) $ids[1]];
    }

    private function generateAutoCmr(Company $company): string
    {
        do {
            $cmr = 'LT-'.$company->id.'-'.now()->format('YmdHis').'-'.random_int(10, 99);
        } while (LandTrip::query()->where('cmr_number', $cmr)->exists());

        return $cmr;
    }

    /**
     * @param  array{
     *     cmr_number: string,
     *     driver_name: string,
     *     from_country_id: int,
     *     to_country_id: int,
     *     departure_date: string,
     *     arrival_date?: string|null,
     *     company_id: int,
     *     freight_amount?: float|int|string,
     *     currency?: string,
     *     voyage_id?: int|null,
     *     notes?: string|null,
     *     cars?: list<array<string, mixed>>
     * }  $data
     */
    public function create(array $data, User $actor): LandTrip
    {
        $this->assertDifferentCountries((int) $data['from_country_id'], (int) $data['to_country_id']);

        return DB::transaction(function () use ($data, $actor): LandTrip {
            $trip = LandTrip::query()->create([
                'cmr_number' => $this->nullableString($data['cmr_number'] ?? null) ?? $this->generateAutoCmr(
                    Company::query()->findOrFail((int) $data['company_id'])
                ),
                'driver_name' => $this->nullableString($data['driver_name'] ?? null),
                'from_country_id' => $data['from_country_id'],
                'to_country_id' => $data['to_country_id'],
                'departure_date' => $data['departure_date'],
                'arrival_date' => $data['arrival_date'] ?? null,
                'company_id' => $data['company_id'],
                'freight_amount' => $data['freight_amount'] ?? 0,
                'currency' => $data['currency'] ?? Currency::USD->value,
                'status' => LandTripStatus::Draft->value,
                'voyage_id' => $data['voyage_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->syncCars($trip, $data['cars'] ?? []);

            return $trip->fresh($this->defaultRelations());
        });
    }

    /**
     * @param  array{
     *     cmr_number: string,
     *     driver_name: string,
     *     from_country_id: int,
     *     to_country_id: int,
     *     departure_date: string,
     *     arrival_date?: string|null,
     *     company_id: int,
     *     freight_amount?: float|int|string,
     *     currency?: string,
     *     voyage_id?: int|null,
     *     notes?: string|null,
     *     cars?: list<array<string, mixed>>
     * }  $data
     */
    public function update(LandTrip $trip, array $data): LandTrip
    {
        $this->assertEditable($trip);
        $this->assertDifferentCountries((int) $data['from_country_id'], (int) $data['to_country_id']);

        return DB::transaction(function () use ($trip, $data): LandTrip {
            $trip->update([
                'cmr_number' => $this->nullableString($data['cmr_number'] ?? null) ?? $trip->cmr_number,
                'driver_name' => $this->nullableString($data['driver_name'] ?? null),
                'from_country_id' => $data['from_country_id'],
                'to_country_id' => $data['to_country_id'],
                'departure_date' => $data['departure_date'],
                'arrival_date' => $data['arrival_date'] ?? null,
                'company_id' => $data['company_id'],
                'freight_amount' => $data['freight_amount'] ?? 0,
                'currency' => $data['currency'] ?? $trip->currency->value,
                'voyage_id' => $data['voyage_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (array_key_exists('cars', $data)) {
                $this->syncCars($trip, $data['cars'] ?? []);
            }

            return $trip->fresh($this->defaultRelations());
        });
    }

    public function transition(LandTrip $trip, LandTripStatus $status): LandTrip
    {
        $allowed = $trip->status->allowedTransitions();

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change status from {$trip->status->value} to {$status->value}.",
            ]);
        }

        $trip->update(['status' => $status->value]);

        return $trip->fresh($this->defaultRelations());
    }

    public function delete(LandTrip $trip, User $actor): void
    {
        $this->assertEditable($trip);

        Log::info('Land trip deleted.', [
            'land_trip_id' => $trip->id,
            'cmr_number' => $trip->cmr_number,
            'deleted_by' => $actor->id,
        ]);

        $trip->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncCars(LandTrip $trip, array $rows): void
    {
        $this->assertEditable($trip);

        $normalized = [];
        $seenChassis = [];

        foreach ($rows as $index => $row) {
            $consignee = trim((string) ($row['consignee_name'] ?? ''));
            $chassis = $this->normalizeChassis($row['chassis_no'] ?? null);

            if ($consignee === '' && $chassis === null && empty($row['voyage_car_id'])) {
                continue;
            }

            if ($consignee === '') {
                throw ValidationException::withMessages([
                    "cars.{$index}.consignee_name" => 'Consignee is required for each car row.',
                ]);
            }

            if ($chassis !== null) {
                if (isset($seenChassis[$chassis])) {
                    throw ValidationException::withMessages([
                        "cars.{$index}.chassis_no" => 'This chassis number is already used.',
                    ]);
                }
                $seenChassis[$chassis] = true;
            }

            $voyageCarId = ! empty($row['voyage_car_id']) ? (int) $row['voyage_car_id'] : null;
            $voyageCar = null;

            if ($voyageCarId) {
                $voyageCar = VoyageCar::query()->whereKey($voyageCarId)->first();
                if (! $voyageCar) {
                    throw ValidationException::withMessages([
                        "cars.{$index}.voyage_car_id" => 'Selected voyage car was not found.',
                    ]);
                }

                if ($trip->voyage_id && (int) $voyageCar->voyage_id !== (int) $trip->voyage_id) {
                    throw ValidationException::withMessages([
                        "cars.{$index}.voyage_car_id" => 'Selected car does not belong to the linked sea voyage.',
                    ]);
                }

                $chassis = $chassis ?: $this->normalizeChassis($voyageCar->chassis_no);
                if ($consignee === '' || $consignee === trim((string) ($row['consignee_name'] ?? ''))) {
                    $consignee = $consignee !== '' ? $consignee : (string) $voyageCar->consignee_name;
                }
            }

            $carId = $chassis ? $this->findOrCreateCar($chassis, $row['description'] ?? $voyageCar?->description)->id : null;

            $normalized[] = [
                'voyage_car_id' => $voyageCarId,
                'car_id' => $carId,
                'chassis_no' => $chassis,
                'cmr_waybill' => $this->nullableString($row['cmr_waybill'] ?? null),
                'consignee_name' => $consignee,
                'description' => $row['description'] ?? $voyageCar?->description,
                'weight' => $row['weight'] ?? $voyageCar?->weight,
                'price' => round((float) ($row['price'] ?? 0), 2),
                'notes' => $row['notes'] ?? null,
                'location_status_id' => ! empty($row['location_status_id']) ? (int) $row['location_status_id'] : null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        $occupied = $this->occupiedChassisSet($trip->cars()->pluck('id')->all());
        foreach ($normalized as $index => $payload) {
            $chassis = $payload['chassis_no'] ?? null;
            if ($chassis && isset($occupied[$chassis])) {
                throw ValidationException::withMessages([
                    "cars.{$index}.chassis_no" => 'This chassis number is already used.',
                ]);
            }
        }

        $trip->cars()->delete();

        foreach ($normalized as $payload) {
            $trip->cars()->create($payload);
        }
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function voyageOptions(): array
    {
        return Voyage::query()
            ->with('ship:id,name')
            ->latest('sailing_date')
            ->limit(200)
            ->get(['id', 'voyage_number', 'ship_id'])
            ->map(fn (Voyage $voyage) => [
                'id' => $voyage->id,
                'label' => $voyage->ship?->name
                    ? "{$voyage->voyage_number} · {$voyage->ship->name}"
                    : $voyage->voyage_number,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function voyageCarOptions(?int $voyageId): array
    {
        if (! $voyageId) {
            return [];
        }

        return VoyageCar::query()
            ->where('voyage_id', $voyageId)
            ->orderBy('id')
            ->get(['id', 'chassis_no', 'consignee_name', 'description', 'weight'])
            ->map(fn (VoyageCar $car) => [
                'id' => $car->id,
                'chassis_no' => $car->chassis_no,
                'consignee_name' => $car->consignee_name,
                'description' => $car->description,
                'weight' => $car->weight !== null ? (string) $car->weight : null,
                'label' => trim(($car->chassis_no ?: '—').' · '.$car->consignee_name),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(LandTrip $trip, bool $detailed = false): array
    {
        $trip->loadMissing([
            'fromCountry:id,name,name_ar',
            'toCountry:id,name,name_ar',
            'company:id,name',
            'voyage:id,voyage_number',
            'journalEntry:id,voucher_number,status',
            'creator:id,name',
        ]);

        $payload = [
            'id' => $trip->id,
            'cmr_number' => $trip->cmr_number,
            'driver_name' => $trip->driver_name,
            'from_country_id' => $trip->from_country_id,
            'to_country_id' => $trip->to_country_id,
            'from_country' => $trip->fromCountry?->localizedName(),
            'to_country' => $trip->toCountry?->localizedName(),
            'route' => trim(($trip->fromCountry?->localizedName() ?? '—').' → '.($trip->toCountry?->localizedName() ?? '—')),
            'departure_date' => $trip->departure_date?->format('Y-m-d'),
            'arrival_date' => $trip->arrival_date?->format('Y-m-d'),
            'company_id' => $trip->company_id,
            'company_name' => $trip->company?->name,
            'freight_amount' => number_format((float) $trip->freight_amount, 2, '.', ''),
            'currency' => $trip->currency->value,
            'status' => $trip->status->value,
            'status_label' => $trip->status->label(),
            'status_tone' => $trip->status->tone(),
            'voyage_id' => $trip->voyage_id,
            'voyage_number' => $trip->voyage?->voyage_number,
            'notes' => $trip->notes,
            'journal_entry_id' => $trip->journal_entry_id,
            'journal_voucher' => $trip->journalEntry?->voucher_number,
            'is_posted' => $trip->isPosted(),
            'is_editable' => $trip->isEditable(),
            'cars_count' => $trip->cars_count ?? $trip->cars()->count(),
            'created_by_name' => $trip->creator?->name,
        ];

        if ($detailed) {
            $trip->loadMissing([
                'cars' => fn ($query) => $query
                    ->with('locationStatus:id,code,name,name_ar,name_ckb,row_tone,color')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ]);
            $payload['cars'] = $trip->cars->map(fn (LandTripCar $car) => $this->transformCar($car))->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function transformCar(LandTripCar $car): array
    {
        $car->loadMissing('locationStatus:id,code,name,name_ar,name_ckb,row_tone,color');

        return [
            'id' => $car->id,
            'voyage_car_id' => $car->voyage_car_id,
            'car_id' => $car->car_id,
            'chassis_no' => $car->chassis_no,
            'cmr_waybill' => $car->cmr_waybill,
            'consignee_name' => $car->consignee_name,
            'description' => $car->description,
            'weight' => $car->weight !== null ? (string) $car->weight : null,
            'price' => number_format((float) $car->price, 2, '.', ''),
            'notes' => $car->notes,
            'location_status_id' => $car->location_status_id,
            'location_status_code' => $car->locationStatus?->code,
            'location_status_label' => $car->locationStatus?->localizedName(),
            'location_status_tone' => $car->locationStatus?->row_tone?->value ?? 'neutral',
            'location_status_color' => $car->locationStatus?->resolvedColor(),
            'land_trip_id' => $car->land_trip_id,
            'sort_order' => $car->sort_order,
        ];
    }

    /**
     * @return list<array{id: int, code: string, label: string, row_tone: string}>
     */
    public function carStatusOptions(): array
    {
        return $this->carStatusService->activeOptions();
    }

    /**
     * @return list<string>
     */
    private function defaultRelations(): array
    {
        return [
            'fromCountry:id,name,name_ar',
            'toCountry:id,name,name_ar',
            'company:id,name',
            'voyage:id,voyage_number',
            'journalEntry:id,voucher_number,status',
            'cars',
            'creator:id,name',
        ];
    }

    /**
     * @param  list<int>  $ignoreIds
     * @return array<string, true>
     */
    private function occupiedChassisSet(array $ignoreIds = []): array
    {
        $query = LandTripCar::query()->whereNotNull('chassis_no');
        if ($ignoreIds !== []) {
            $query->whereNotIn('id', $ignoreIds);
        }

        $seen = [];
        foreach ($query->pluck('chassis_no') as $value) {
            $normalized = $this->normalizeChassis($value);
            if ($normalized !== null) {
                $seen[$normalized] = true;
            }
        }

        return $seen;
    }

    private function findOrCreateCar(string $chassis, ?string $description): Car
    {
        return Car::query()->firstOrCreate(
            ['vin' => $chassis],
            ['description' => $description]
        );
    }

    private function normalizeChassis(mixed $value): ?string
    {
        $chassis = strtoupper((string) preg_replace('/[\s\-]/', '', trim((string) ($value ?? ''))));

        return $chassis === '' ? null : $chassis;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function assertDifferentCountries(int $from, int $to): void
    {
        if ($from === $to) {
            throw ValidationException::withMessages([
                'to_country_id' => 'Origin and destination countries must be different.',
            ]);
        }
    }

    private function assertEditable(LandTrip $trip): void
    {
        if (! $trip->isEditable()) {
            throw ValidationException::withMessages([
                'land_trip' => 'Posted or closed land trips cannot be edited.',
            ]);
        }
    }
}
