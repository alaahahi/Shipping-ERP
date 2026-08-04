<?php

namespace App\Http\Requests\ShipOwnerships;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipOwnershipRequest extends FormRequest
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
            'owner_id' => ['nullable', 'integer', 'exists:owners,id'],
            'owner_name' => ['nullable', 'string', 'max:255', 'required_without:owner_id'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'share_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'is_managing' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
