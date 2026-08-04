<?php

namespace App\Http\Requests\VoyageExpenses;

use App\Enums\Currency;
use App\Enums\VoyageExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVoyageExpenseRequest extends FormRequest
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
            'expense_type' => ['required', Rule::enum(VoyageExpenseType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
