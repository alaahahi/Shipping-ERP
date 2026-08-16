<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use App\Support\ChassisLetterO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyLandCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('chassis_no')) {
            $this->merge([
                'chassis_no' => ChassisLetterO::replace(
                    strtoupper((string) preg_replace('/[\s\-]/', '', trim((string) $this->input('chassis_no'))))
                ),
            ]);
        }

        if ($this->has('year') && $this->input('year') === '') {
            $this->merge(['year' => null]);
        }

        if ($this->has('price') && $this->input('price') === '') {
            $this->merge(['price' => 0]);
        }
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
            'year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'description' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'location_status_id' => ['nullable', 'integer', 'exists:land_trip_car_statuses,id'],
        ];
    }
}
