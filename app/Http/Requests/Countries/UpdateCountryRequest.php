<?php

namespace App\Http\Requests\Countries;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iso_code' => $this->iso_code === '' ? null : $this->iso_code,
            'latitude' => $this->latitude === '' ? null : $this->latitude,
            'longitude' => $this->longitude === '' ? null : $this->longitude,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countryId = $this->route('country')?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('countries', 'name')->ignore($countryId)],
            'name_ar' => ['required', 'string', 'max:120'],
            'iso_code' => ['nullable', 'string', 'max:8', Rule::unique('countries', 'iso_code')->ignore($countryId)],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
