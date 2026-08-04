<?php

namespace App\Http\Requests\VoyageExpenses;

use Illuminate\Foundation\Http\FormRequest;

class PostVoyageExpenseRequest extends FormRequest
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
            'payment_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ];
    }
}
