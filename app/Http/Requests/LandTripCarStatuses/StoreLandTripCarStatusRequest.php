<?php

namespace App\Http\Requests\LandTripCarStatuses;

use App\Enums\LandTripCarRowTone;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLandTripCarStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->country_id === '' || $this->country_id === '0') {
            $this->merge(['country_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:land_trip_car_statuses,code'],
            'name' => ['required', 'string', 'max:180'],
            'name_ar' => ['nullable', 'string', 'max:180'],
            'name_ckb' => ['nullable', 'string', 'max:180'],
            'row_tone' => ['required', 'string', Rule::enum(LandTripCarRowTone::class)],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'match_aliases' => ['nullable', 'array'],
            'match_aliases.*' => ['string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        ];
    }
}
