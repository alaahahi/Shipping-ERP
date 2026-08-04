<?php

namespace App\Http\Requests\VoyageCompanies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoyageCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'company_name' => [
                Rule::requiredIf(fn () => blank($this->input('company_id'))),
                'nullable',
                'string',
                'max:180',
            ],
            'contact_name' => ['nullable', 'string', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'shipping_price_per_car' => ['nullable', 'numeric', 'min:0'],
            'shipping_price_aed' => ['nullable', 'numeric', 'min:0'],
            'clearance_per_car' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
