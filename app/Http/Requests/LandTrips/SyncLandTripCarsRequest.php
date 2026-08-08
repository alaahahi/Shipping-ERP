<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SyncLandTripCarsRequest extends FormRequest
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
            'cars.*.voyage_car_id' => ['nullable', 'integer', 'exists:voyage_cars,id'],
            'cars.*.chassis_no' => ['nullable', 'string', 'max:64'],
            'cars.*.consignee_name' => ['nullable', 'string', 'max:180'],
            'cars.*.description' => ['nullable', 'string', 'max:255'],
            'cars.*.weight' => ['nullable', 'numeric', 'min:0'],
            'cars.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
