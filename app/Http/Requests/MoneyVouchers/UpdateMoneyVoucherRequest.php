<?php

namespace App\Http\Requests\MoneyVouchers;

use App\Enums\Currency;
use App\Enums\MoneyVoucherType;
use App\Models\MoneyVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMoneyVoucherRequest extends FormRequest
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
        /** @var MoneyVoucher|null $voucher */
        $voucher = $this->route('money_voucher');
        $isReceipt = $voucher?->type === MoneyVoucherType::Receipt;

        return [
            'voucher_date' => ['required', 'date'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'company_id' => [
                Rule::requiredIf($isReceipt),
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
