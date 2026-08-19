<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class TransferCompanyLandCarsRequest extends FormRequest
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
            'to_company_id' => ['required', 'integer', 'exists:companies,id', 'different:source_company'],
            'car_ids' => ['required', 'array', 'min:1'],
            'car_ids.*' => ['integer', 'exists:land_trip_cars,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_company' => $this->route('company')?->id,
        ]);
    }
}
