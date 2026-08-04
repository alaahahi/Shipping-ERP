<?php

namespace App\Http\Requests\Ships;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'flag' => ['nullable', 'string', 'max:100'],
            'imo_number' => ['nullable', 'string', 'max:20', 'unique:ships,imo_number'],
            'call_sign' => ['nullable', 'string', 'max:30'],
            'default_captain' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
