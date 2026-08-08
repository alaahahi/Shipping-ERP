<?php

namespace App\Http\Requests\Companies;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyDirectChargeRequest extends FormRequest
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
            'charge_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'credit_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }
}
