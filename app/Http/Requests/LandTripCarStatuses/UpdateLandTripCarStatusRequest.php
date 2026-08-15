<?php

namespace App\Http\Requests\LandTripCarStatuses;

use App\Enums\LandTripCarRowTone;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLandTripCarStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $status = $this->route('land_trip_car_status');

        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('land_trip_car_statuses', 'code')->ignore($status)],
            'name' => ['required', 'string', 'max:180'],
            'name_ar' => ['nullable', 'string', 'max:180'],
            'name_ckb' => ['nullable', 'string', 'max:180'],
            'row_tone' => ['required', 'string', Rule::enum(LandTripCarRowTone::class)],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'match_aliases' => ['nullable', 'array'],
            'match_aliases.*' => ['string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
