<?php

namespace App\Http\Requests\ShipPartnerContributions;

use Illuminate\Foundation\Http\FormRequest;

class PostShipPartnerContributionRequest extends FormRequest
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
            'payment_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ];
    }
}
