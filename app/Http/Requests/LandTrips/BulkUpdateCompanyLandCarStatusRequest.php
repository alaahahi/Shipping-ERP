<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCompanyLandCarStatusRequest extends FormRequest
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
            'location_status_id' => ['required', 'integer', 'exists:land_trip_car_statuses,id'],
            'scope' => ['required', 'string', Rule::in(['selected'])],
            'car_ids' => ['required', 'array', 'min:1'],
            'car_ids.*' => ['integer', 'exists:land_trip_cars,id'],
        ];
    }
}
