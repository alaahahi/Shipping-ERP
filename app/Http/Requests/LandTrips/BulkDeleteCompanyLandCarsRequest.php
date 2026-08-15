<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteCompanyLandCarsRequest extends FormRequest
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
            'car_ids' => ['required', 'array', 'min:1'],
            'car_ids.*' => ['integer', 'exists:land_trip_cars,id'],
        ];
    }
}
