<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\LandTripStatus;
use App\Models\Car;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VoyageCar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LandTripService
{
    /**
     * @param  array{search?: string|null, status?: string|null, company_id?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LandTrip::query()
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
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        return $query->paginate($perPage)->withQueryString();
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
                'cmr_number' => trim($data['cmr_number']),
                'driver_name' => trim($data['driver_name']),
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
                'cmr_number' => trim($data['cmr_number']),
                'driver_name' => trim($data['driver_name']),
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
                        "cars.{$index}.chassis_no" => 'Duplicate chassis number on this land trip.',
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
                'consignee_name' => $consignee,
                'description' => $row['description'] ?? $voyageCar?->description,
                'weight' => $row['weight'] ?? $voyageCar?->weight,
                'notes' => $row['notes'] ?? null,
            ];
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
            $trip->loadMissing(['cars']);
            $payload['cars'] = $trip->cars->map(fn (LandTripCar $car) => $this->transformCar($car))->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function transformCar(LandTripCar $car): array
    {
        return [
            'id' => $car->id,
            'voyage_car_id' => $car->voyage_car_id,
            'car_id' => $car->car_id,
            'chassis_no' => $car->chassis_no,
            'consignee_name' => $car->consignee_name,
            'description' => $car->description,
            'weight' => $car->weight !== null ? (string) $car->weight : null,
            'notes' => $car->notes,
        ];
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

    private function findOrCreateCar(string $chassis, ?string $description): Car
    {
        return Car::query()->firstOrCreate(
            ['vin' => $chassis],
            ['description' => $description]
        );
    }

    private function normalizeChassis(mixed $value): ?string
    {
        $chassis = strtoupper(trim((string) ($value ?? '')));

        return $chassis === '' ? null : $chassis;
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
