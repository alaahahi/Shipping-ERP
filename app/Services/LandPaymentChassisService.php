<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LandPaymentChassis;
use App\Models\LandTripCar;
use App\Models\User;
use App\Support\ChassisLetterO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LandPaymentChassisService
{
    /**
     * @param  list<int>  $carIds
     * @param  list<string>  $lines
     * @return array{assigned: list<array{id: int, land_trip_car_id: int, chassis_no: string}>, skipped: list<array{line: string, reason: string}>}
     */
    public function assign(
        Company $company,
        Model $payable,
        User $actor,
        array $carIds = [],
        array $lines = []
    ): array {
        $this->assertPayableCompany($company, $payable);

        $resolved = $this->resolveCars($company, $carIds, $lines);

        if ($carIds !== [] && $resolved['foreign'] > 0) {
            throw ValidationException::withMessages([
                'car_ids' => 'Selected cars must belong to this company.',
            ]);
        }

        return DB::transaction(function () use ($company, $payable, $actor, $resolved): array {
            $previous = LandPaymentChassis::query()
                ->where('payable_type', $payable->getMorphClass())
                ->where('payable_id', $payable->getKey())
                ->get();

            $previousChassis = $previous->pluck('chassis_no')->all();

            LandPaymentChassis::query()
                ->where('payable_type', $payable->getMorphClass())
                ->where('payable_id', $payable->getKey())
                ->delete();

            $rows = [];
            foreach ($resolved['cars'] as $car) {
                $rows[] = LandPaymentChassis::query()->create([
                    'company_id' => $company->id,
                    'payable_type' => $payable->getMorphClass(),
                    'payable_id' => $payable->getKey(),
                    'land_trip_car_id' => $car->id,
                    'chassis_no' => $car->chassis_no,
                    'created_by' => $actor->id,
                ]);
            }

            Log::info('Land payment chassis assigned.', [
                'company_id' => $company->id,
                'payable_type' => $payable->getMorphClass(),
                'payable_id' => $payable->getKey(),
                'user_id' => $actor->id,
                'previous_chassis' => $previousChassis,
                'assigned_chassis' => collect($rows)->pluck('chassis_no')->all(),
                'skipped' => $resolved['skipped'],
            ]);

            return [
                'assigned' => collect($rows)->map(fn (LandPaymentChassis $row) => $this->transformRow($row))->values()->all(),
                'skipped' => $resolved['skipped'],
            ];
        });
    }

    /**
     * @return list<array{id: int, land_trip_car_id: int|null, chassis_no: string}>
     */
    public function forPayable(Model $payable): array
    {
        return LandPaymentChassis::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (LandPaymentChassis $row) => $this->transformRow($row))
            ->values()
            ->all();
    }

    /**
     * @param  list<CompanyWalletEntry|LandDriverPayment>  $payables
     * @return array<string, list<array{id: int, land_trip_car_id: int|null, chassis_no: string}>>
     */
    public function mapForPayables(iterable $payables): array
    {
        $payables = collect($payables)->filter();
        if ($payables->isEmpty()) {
            return [];
        }

        $groups = $payables->groupBy(fn (Model $model) => $model->getMorphClass());
        $map = [];

        foreach ($groups as $type => $models) {
            $ids = $models->map(fn (Model $model) => (int) $model->getKey())->all();
            $rows = LandPaymentChassis::query()
                ->where('payable_type', $type)
                ->whereIn('payable_id', $ids)
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $key = $type.':'.$row->payable_id;
                $map[$key][] = $this->transformRow($row);
            }
        }

        return $map;
    }

    /**
     * @return array{id: int, land_trip_car_id: int|null, chassis_no: string}
     */
    public function transformRow(LandPaymentChassis $row): array
    {
        return [
            'id' => $row->id,
            'land_trip_car_id' => $row->land_trip_car_id,
            'chassis_no' => $row->chassis_no,
        ];
    }

    /**
     * @param  list<int>  $carIds
     * @param  list<string>  $lines
     * @return array{cars: Collection<int, LandTripCar>, skipped: list<array{line: string, reason: string}>, foreign: int}
     */
    public function resolveCars(Company $company, array $carIds, array $lines): array
    {
        $companyCars = LandTripCar::query()
            ->whereHas('landTrip', fn ($builder) => $builder->where('company_id', $company->id))
            ->whereNotNull('chassis_no')
            ->where('chassis_no', '!=', '')
            ->get();

        $byId = $companyCars->keyBy('id');
        $matched = collect();
        $skipped = [];
        $foreign = 0;

        foreach (array_values(array_unique(array_map('intval', $carIds))) as $id) {
            if ($id < 1) {
                continue;
            }
            $car = $byId->get($id);
            if ($car) {
                $matched->put($car->id, $car);
                continue;
            }
            $existsElsewhere = LandTripCar::query()->whereKey($id)->exists();
            if ($existsElsewhere) {
                $foreign++;
            }
        }

        foreach ($this->normalizeLines($lines) as $line) {
            $hits = $this->matchLine($companyCars, $line);
            if ($hits->count() === 1) {
                $car = $hits->first();
                $matched->put($car->id, $car);
                continue;
            }
            if ($hits->count() > 1) {
                $skipped[] = ['line' => $line, 'reason' => 'ambiguous'];
                continue;
            }
            $skipped[] = ['line' => $line, 'reason' => 'missing'];
        }

        return [
            'cars' => $matched->values(),
            'skipped' => $skipped,
            'foreign' => $foreign,
        ];
    }

    /**
     * @param  list<string>|string  $raw
     * @return list<string>
     */
    public function normalizeLines(array|string $raw): array
    {
        $text = is_array($raw) ? implode("\n", $raw) : $raw;

        return collect(preg_split('/\r\n|\r|\n/', (string) $text) ?: [])
            ->map(fn ($line) => $this->normalizeChassis($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LandTripCar>  $cars
     * @return \Illuminate\Support\Collection<int, LandTripCar>
     */
    private function matchLine($cars, string $needle)
    {
        $exact = $cars->filter(
            fn (LandTripCar $car): bool => $this->normalizeChassis($car->chassis_no) === $needle
        );
        if ($exact->isNotEmpty()) {
            return $exact->values();
        }

        $suffix = $this->lastSixDigits($needle);
        if ($suffix === null) {
            return collect();
        }

        return $cars
            ->filter(function (LandTripCar $car) use ($suffix): bool {
                $normalized = $this->normalizeChassis($car->chassis_no);
                if ($normalized === null) {
                    return false;
                }

                return $this->lastSixDigits($normalized) === $suffix;
            })
            ->values();
    }

    private function normalizeChassis(mixed $value): ?string
    {
        $chassis = ChassisLetterO::replace(strtoupper((string) preg_replace('/[\s\-]/', '', trim((string) ($value ?? '')))));

        return $chassis === '' ? null : $chassis;
    }

    private function lastSixDigits(string $normalized): ?string
    {
        $tail = substr($normalized, -6);
        if (preg_match('/^\d{6}$/', $tail) === 1) {
            return $tail;
        }

        if (preg_match('/(\d{6,})$/', $normalized, $matches) === 1) {
            return substr($matches[1], -6);
        }

        return null;
    }

    private function assertPayableCompany(Company $company, Model $payable): void
    {
        $companyId = (int) ($payable->company_id ?? 0);
        if ($companyId !== (int) $company->id) {
            abort(404);
        }
    }
}
