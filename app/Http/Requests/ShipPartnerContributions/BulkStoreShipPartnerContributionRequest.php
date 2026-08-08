<?php

namespace App\Http\Requests\ShipPartnerContributions;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreShipPartnerContributionRequest extends FormRequest
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
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.owner_id' => ['required', 'integer', 'exists:owners,id'],
            'rows.*.contribution_date' => ['required', 'date'],
            'rows.*.amount' => ['required', 'numeric', 'min:0.01'],
            'rows.*.currency' => ['required', Rule::enum(Currency::class)],
            'rows.*.description' => ['nullable', 'string', 'max:255'],
            'rows.*.reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
