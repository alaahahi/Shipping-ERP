<?php

namespace App\Http\Requests\Countries;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->iso_code === '') {
            $this->merge(['iso_code' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'unique:countries,name'],
            'name_ar' => ['required', 'string', 'max:120'],
            'iso_code' => ['nullable', 'string', 'max:8', Rule::unique('countries', 'iso_code')],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
