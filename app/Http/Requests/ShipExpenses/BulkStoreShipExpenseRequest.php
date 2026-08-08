<?php

namespace App\Http\Requests\ShipExpenses;

use App\Enums\Currency;
use App\Enums\ShipExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreShipExpenseRequest extends FormRequest
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
            'rows.*.expense_type' => ['required', Rule::enum(ShipExpenseType::class)],
            'rows.*.amount' => ['required', 'numeric', 'min:0.01'],
            'rows.*.currency' => ['required', Rule::enum(Currency::class)],
            'rows.*.expense_date' => ['required', 'date'],
            'rows.*.vendor' => ['nullable', 'string', 'max:180'],
            'rows.*.reference' => ['nullable', 'string', 'max:120'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
