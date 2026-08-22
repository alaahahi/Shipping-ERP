<?php

namespace App\Http\Requests\LandTrips;

use App\Models\LandTrip;
use App\Services\LandTripService;
use Illuminate\Foundation\Http\FormRequest;

class CompanyLandCarsOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LandTrip::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('car_ids', []);
        if (is_string($raw)) {
            $raw = $raw === '' ? [] : preg_split('/\s*,\s*/', $raw);
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, is_array($raw) ? $raw : []),
            static fn (int $id): bool => $id > 0
        )));

        $locationId = $this->input('location_status_id');

        $this->merge([
            'car_ids' => $ids,
            'location_status_id' => $locationId === '' || $locationId === null ? null : $locationId,
            'search' => trim((string) $this->input('search', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'location_status_id' => ['nullable', 'integer', 'exists:land_trip_car_statuses,id'],
            'sort' => ['nullable', 'string', 'max:40'],
            'car_ids' => ['nullable', 'array', 'max:5000'],
            'car_ids.*' => ['integer', 'exists:land_trip_cars,id'],
        ];
    }

    /**
     * @return array{search: string, location_status_id: string, sort: string, car_ids: list<int>}
     */
    public function filters(): array
    {
        return [
            'search' => trim($this->string('search')->toString()),
            'location_status_id' => $this->filled('location_status_id')
                ? (string) $this->integer('location_status_id')
                : '',
            'sort' => app(LandTripService::class)->normalizeCompanyCarSort($this->string('sort')->toString()),
            'car_ids' => array_values(array_map('intval', $this->input('car_ids', []))),
        ];
    }
}
