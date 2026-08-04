<?php

namespace App\Http\Requests\VoyageCompanies;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVoyageCompanyRequest extends FormRequest
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
            'shipping_price_per_car' => ['nullable', 'numeric', 'min:0'],
            'shipping_price_aed' => ['nullable', 'numeric', 'min:0'],
            'clearance_per_car' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
