<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SyncCompanyLandCarsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cars' => ['required', 'array'],
            'cars.*.id' => ['nullable', 'integer', 'exists:land_trip_cars,id'],
            'cars.*.voyage_car_id' => ['nullable', 'integer', 'exists:voyage_cars,id'],
            'cars.*.chassis_no' => ['nullable', 'string', 'max:64'],
            'cars.*.cmr_waybill' => ['nullable', 'string', 'max:80'],
            'cars.*.consignee_name' => ['nullable', 'string', 'max:180'],
            'cars.*.model' => ['nullable', 'string', 'max:180'],
            'cars.*.color' => ['nullable', 'string', 'max:80'],
            'cars.*.year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'cars.*.description' => ['nullable', 'string', 'max:255'],
            'cars.*.weight' => ['nullable', 'numeric', 'min:0'],
            'cars.*.price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'cars.*.notes' => ['nullable', 'string', 'max:1000'],
            'cars.*.location_status_id' => ['nullable', 'integer', 'exists:land_trip_car_statuses,id'],
            'cars.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
