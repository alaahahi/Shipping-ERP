<?php

namespace App\Http\Requests\MoneyVouchers;

use App\Enums\Currency;
use App\Enums\MoneyVoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyVoucherRequest extends FormRequest
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
            'type' => ['required', Rule::enum(MoneyVoucherType::class)],
            'voucher_date' => ['required', 'date'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'company_id' => [
                Rule::requiredIf(fn () => $this->input('type') === MoneyVoucherType::Receipt->value),
                'nullable',
                'integer',
                'exists:companies,id',
            ],
            'voyage_id' => ['nullable', 'integer', 'exists:voyages,id'],
            'counterparty' => ['nullable', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.voyage_id' => ['required_with:allocations', 'integer', 'exists:voyages,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
        ];
    }
}
