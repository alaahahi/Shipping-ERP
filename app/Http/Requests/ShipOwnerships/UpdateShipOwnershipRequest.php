<?php

namespace App\Http\Requests\ShipOwnerships;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipOwnershipRequest extends FormRequest
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
            'share_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'is_managing' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
