<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Voyage;
use App\Models\VoyageCar;
use App\Models\VoyageCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoyageCarService
{
    public function __construct(
        private readonly CompanyWhatsappNotificationService $whatsappNotificationService
    ) {}

    /**
     * @param  array{
     *     voyage_company_id: int,
     *     chassis_no?: string|null,
     *     consignee_name: string,
     *     shipper_name?: string|null,
     *     description?: string|null,
     *     weight?: float|int|string|null,
     *     code?: string|null,
     *     row_number?: int|null
     * }  $data
     */
    public function create(Voyage $voyage, array $data): VoyageCar
    {
        $this->assertVoyageEditable($voyage);
        $company = $this->resolveCompany($voyage, (int) $data['voyage_company_id']);
        $chassis = $this->normalizeChassis($data['chassis_no'] ?? null);
        $this->assertUniqueChassis($voyage, $chassis);

        return DB::transaction(function () use ($voyage, $company, $data, $chassis): VoyageCar {
            $carId = $chassis ? $this->findOrCreateCar($chassis, $data['description'] ?? null)?->id : null;

            $car = $voyage->cars()->create([
                'voyage_company_id' => $company->id,
                'car_id' => $carId,
                'chassis_no' => $chassis,
                'consignee_name' => trim((string) $data['consignee_name']),
                'shipper_name' => $data['shipper_name'] ?? $company->company_name,
                'description' => $data['description'] ?? null,
                'weight' => $data['weight'] ?? null,
                'code' => $data['code'] ?? null,
                'row_number' => $data['row_number'] ?? null,
            ]);

            $this->whatsappNotificationService->notifyVoyageCarLoaded($company, $voyage);

            return $car;
        });
    }

    /**
     * @param  array{
     *     voyage_company_id: int,
     *     chassis_no?: string|null,
     *     consignee_name: string,
     *     shipper_name?: string|null,
     *     description?: string|null,
     *     weight?: float|int|string|null,
     *     code?: string|null
     * }  $data
     */
    public function update(VoyageCar $voyageCar, array $data): VoyageCar
    {
        $voyageCar->loadMissing('voyage');
        $this->assertVoyageEditable($voyageCar->voyage);
        $company = $this->resolveCompany($voyageCar->voyage, (int) $data['voyage_company_id']);
        $chassis = $this->normalizeChassis($data['chassis_no'] ?? null);
        $this->assertUniqueChassis($voyageCar->voyage, $chassis, $voyageCar->id);

        return DB::transaction(function () use ($voyageCar, $company, $data, $chassis): VoyageCar {
            $carId = $chassis ? $this->findOrCreateCar($chassis, $data['description'] ?? null)?->id : null;

            $voyageCar->update([
                'voyage_company_id' => $company->id,
                'car_id' => $carId,
                'chassis_no' => $chassis,
                'consignee_name' => trim((string) $data['consignee_name']),
                'shipper_name' => $data['shipper_name'] ?? $company->company_name,
                'description' => $data['description'] ?? null,
                'weight' => $data['weight'] ?? null,
                'code' => $data['code'] ?? null,
            ]);

            return $voyageCar->fresh(['company:id,company_name']);
        });
    }

    public function delete(VoyageCar $voyageCar): void
    {
        $voyageCar->loadMissing('voyage');
        $this->assertVoyageEditable($voyageCar->voyage);
        $voyageCar->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(VoyageCar $car): array
    {
        return [
            'id' => $car->id,
            'voyage_id' => $car->voyage_id,
            'voyage_company_id' => $car->voyage_company_id,
            'company_name' => $car->company?->company_name,
            'car_id' => $car->car_id,
            'chassis_no' => $car->chassis_no,
            'consignee_name' => $car->consignee_name,
            'shipper_name' => $car->shipper_name,
            'description' => $car->description,
            'weight' => $car->weight !== null ? (string) $car->weight : null,
            'code' => $car->code,
            'row_number' => $car->row_number,
        ];
    }

    private function findOrCreateCar(string $chassis, ?string $description): Car
    {
        return Car::query()->firstOrCreate(
            ['vin' => $chassis],
            ['description' => $description]
        );
    }

    private function resolveCompany(Voyage $voyage, int $companyId): VoyageCompany
    {
        $company = VoyageCompany::query()
            ->where('voyage_id', $voyage->id)
            ->whereKey($companyId)
            ->first();

        if (! $company) {
            throw ValidationException::withMessages([
                'voyage_company_id' => 'Selected company does not belong to this voyage.',
            ]);
        }

        return $company;
    }

    private function assertUniqueChassis(Voyage $voyage, ?string $chassis, ?int $ignoreId = null): void
    {
        if ($chassis === null || $chassis === '') {
            return;
        }

        $exists = VoyageCar::query()
            ->where('voyage_id', $voyage->id)
            ->where('chassis_no', $chassis)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'chassis_no' => 'This chassis number already exists on the voyage.',
            ]);
        }
    }

    private function normalizeChassis(mixed $value): ?string
    {
        $chassis = strtoupper(trim((string) ($value ?? '')));

        return $chassis === '' ? null : $chassis;
    }

    private function assertVoyageEditable(?Voyage $voyage): void
    {
        if (! $voyage || ! $voyage->isEditable()) {
            throw ValidationException::withMessages([
                'voyage' => 'Closed voyages cannot accept car changes.',
            ]);
        }
    }
}
