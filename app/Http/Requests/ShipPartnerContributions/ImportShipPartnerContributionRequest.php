<?php

namespace App\Http\Requests\ShipPartnerContributions;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportShipPartnerContributionRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'owner_id' => ['required', 'integer', 'exists:owners,id'],
            'currency' => ['required', Rule::enum(Currency::class)],
        ];
    }
}
