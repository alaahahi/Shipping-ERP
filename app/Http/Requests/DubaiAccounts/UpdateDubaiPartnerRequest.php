<?php

namespace App\Http\Requests\DubaiAccounts;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDubaiPartnerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:180'],
            'contact_name' => ['nullable', 'string', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
