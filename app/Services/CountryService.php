<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CountryService
{
    /**
     * @return Collection<int, Country>
     */
    public function all(): Collection
    {
        return Country::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<array{id: int, label: string, name: string, name_ar: string, iso_code: ?string, latitude: ?float, longitude: ?float, is_active: bool, sort_order: int}>
     */
    public function transformMany(Collection $countries): array
    {
        return $countries->map(fn (Country $country) => $this->transform($country))->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function activeOptions(): array
    {
        return Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Country $country) => [
                'id' => $country->id,
                'label' => $country->localizedName(),
            ])
            ->all();
    }

    /**
     * @return array{id: int, label: string, name: string, name_ar: string, iso_code: ?string, latitude: ?float, longitude: ?float, is_active: bool, sort_order: int}
     */
    public function transform(Country $country): array
    {
        return [
            'id' => $country->id,
            'label' => $country->localizedName(),
            'name' => $country->name,
            'name_ar' => $country->name_ar,
            'iso_code' => $country->iso_code,
            'latitude' => $country->latitude,
            'longitude' => $country->longitude,
            'is_active' => $country->is_active,
            'sort_order' => $country->sort_order,
        ];
    }

    /**
     * @param  array{name: string, name_ar: string, iso_code?: string|null, is_active?: bool, sort_order?: int}  $data
     */
    public function create(array $data): Country
    {
        return Country::query()->create([
            'name' => trim($data['name']),
            'name_ar' => trim($data['name_ar']),
            'iso_code' => $this->nullableCode($data['iso_code'] ?? null),
            'latitude' => $this->nullableCoordinate($data['latitude'] ?? null),
            'longitude' => $this->nullableCoordinate($data['longitude'] ?? null),
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * @param  array{name: string, name_ar: string, iso_code?: string|null, is_active?: bool, sort_order?: int}  $data
     */
    public function update(Country $country, array $data): Country
    {
        $country->update([
            'name' => trim($data['name']),
            'name_ar' => trim($data['name_ar']),
            'iso_code' => $this->nullableCode($data['iso_code'] ?? null),
            'latitude' => $this->nullableCoordinate($data['latitude'] ?? null),
            'longitude' => $this->nullableCoordinate($data['longitude'] ?? null),
            'is_active' => $data['is_active'] ?? $country->is_active,
            'sort_order' => (int) ($data['sort_order'] ?? $country->sort_order),
        ]);

        return $country->fresh();
    }

    public function delete(Country $country): void
    {
        if ($country->tripsFrom()->exists() || $country->tripsTo()->exists() || $country->locationStatuses()->exists()) {
            throw ValidationException::withMessages([
                'country' => 'Cannot delete a country that is used on land trips or locations.',
            ]);
        }

        $country->delete();
    }

    private function nullableCode(mixed $value): ?string
    {
        $code = strtoupper(trim((string) $value));

        return $code === '' ? null : $code;
    }

    private function nullableCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
