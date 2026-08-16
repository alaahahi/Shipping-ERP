<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyLandCarRequest extends FormRequest
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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'chassis_no' => ['nullable', 'string', 'max:64'],
            'cmr_waybill' => ['nullable', 'string', 'max:80'],
            'consignee_name' => ['nullable', 'string', 'max:180'],
            'model' => ['nullable', 'string', 'max:180'],
            'color' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'location_status_id' => ['nullable', 'integer', 'exists:land_trip_car_statuses,id'],
        ];
    }
}
