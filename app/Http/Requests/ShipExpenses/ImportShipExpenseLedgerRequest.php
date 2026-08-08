<?php

namespace App\Http\Requests\ShipExpenses;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportShipExpenseLedgerRequest extends FormRequest
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
            'currency' => ['required', Rule::enum(Currency::class)],
            'paid_by_owner_id' => ['nullable', 'integer', 'exists:owners,id'],
        ];
    }
}
