<?php

namespace App\Http\Requests\DubaiAccounts;

use App\Enums\DubaiEntryKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDubaiEntryRequest extends FormRequest
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
            'entry_date' => ['required', 'date'],
            'doc_no' => ['nullable', 'string', 'max:255'],
            'entry_kind' => ['required', Rule::enum(DubaiEntryKind::class)],
            'transport_qty' => ['nullable', 'numeric', 'min:0'],
            'transport_rate' => ['nullable', 'numeric', 'min:0'],
            'transport_total' => ['nullable', 'numeric', 'min:0'],
            'forklift_qty' => ['nullable', 'numeric', 'min:0'],
            'forklift_rate' => ['nullable', 'numeric', 'min:0'],
            'forklift_total' => ['nullable', 'numeric', 'min:0'],
            'total_debit' => ['nullable', 'numeric', 'min:0'],
            'debit' => ['nullable', 'numeric', 'min:0'],
            'credit' => ['nullable', 'numeric', 'min:0'],
            'usd_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ship_id' => ['nullable', 'integer', 'exists:ships,id'],
            'voyage_id' => ['nullable', 'integer', 'exists:voyages,id'],
        ];
    }
}
