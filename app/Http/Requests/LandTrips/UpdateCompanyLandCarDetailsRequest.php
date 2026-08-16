<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyLandCarDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('year') && $this->input('year') === '') {
            $this->merge(['year' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model' => ['sometimes', 'nullable', 'string', 'max:180'],
            'color' => ['sometimes', 'nullable', 'string', 'max:80'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1980', 'max:2100'],
            'cmr_waybill' => ['sometimes', 'nullable', 'string', 'max:80'],
            'consignee_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
