<?php

namespace App\Http\Requests\ShipExpenses;

use App\Enums\Currency;
use App\Enums\ShipExpenseType;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipExpenseRequest extends FormRequest
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
            'expense_type' => ['required', Rule::enum(ShipExpenseType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'expense_date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_by_owner_id' => ['nullable', 'integer', 'exists:owners,id'],
            'attachment' => AttachmentRules::file(),
            'attachment' => AttachmentRules::file(),
        ];
    }
}
