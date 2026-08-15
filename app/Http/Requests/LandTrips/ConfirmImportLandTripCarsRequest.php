<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmImportLandTripCarsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows');
        if (! is_array($rows)) {
            return;
        }

        $this->merge([
            'rows' => array_map(static function ($row) {
                if (! is_array($row)) {
                    return $row;
                }

                if (($row['location_status_id'] ?? '') === '') {
                    $row['location_status_id'] = null;
                }

                return $row;
            }, $rows),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.row_number' => ['nullable', 'integer', 'min:0'],
            'rows.*.chassis_no' => ['nullable', 'string', 'max:64'],
            'rows.*.cmr_waybill' => ['nullable', 'string', 'max:80'],
            'rows.*.description' => ['nullable', 'string', 'max:255'],
            'rows.*.consignee_name' => ['nullable', 'string', 'max:180'],
            'rows.*.status_text' => ['nullable', 'string', 'max:180'],
            'rows.*.location_status_id' => ['nullable', 'integer', 'exists:land_trip_car_statuses,id'],
        ];
    }
}
