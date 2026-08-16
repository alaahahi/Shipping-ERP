<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Currency;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLandTripRequest extends FormRequest
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
        $tripId = $this->route('land_trip')?->id;

        return [
            'cmr_number' => ['nullable', 'string', 'max:80', Rule::unique('land_trips', 'cmr_number')->ignore($tripId)],
            'driver_name' => ['nullable', 'string', 'max:180'],
            'from_country_id' => ['required', 'integer', 'exists:countries,id'],
            'to_country_id' => ['required', 'integer', 'exists:countries,id', 'different:from_country_id'],
            'departure_date' => ['required', 'date'],
            'arrival_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', Rule::in(Currency::values())],
            'voyage_id' => ['nullable', 'integer', 'exists:voyages,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cars' => ['nullable', 'array'],
            'cars.*.voyage_car_id' => ['nullable', 'integer', 'exists:voyage_cars,id'],
            'cars.*.chassis_no' => ['nullable', 'string', 'max:64'],
            'cars.*.consignee_name' => ['nullable', 'string', 'max:180'],
            'cars.*.model' => ['nullable', 'string', 'max:180'],
            'cars.*.color' => ['nullable', 'string', 'max:80'],
            'cars.*.description' => ['nullable', 'string', 'max:255'],
            'cars.*.weight' => ['nullable', 'numeric', 'min:0'],
            'cars.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
