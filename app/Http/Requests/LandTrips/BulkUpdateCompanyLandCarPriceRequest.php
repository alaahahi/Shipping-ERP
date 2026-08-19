<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCompanyLandCarPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->price === '' || $this->price === null) {
            $this->merge(['price' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'car_ids' => ['required', 'array', 'min:1'],
            'car_ids.*' => ['integer', 'exists:land_trip_cars,id'],
        ];
    }
}
