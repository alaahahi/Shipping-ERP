<?php

namespace App\Http\Requests\Accounts;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AccountingManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $accountId = $this->route('account')?->id;

        return [
            'type' => ['required', 'in:receipt,payment'],
            'counterpart_account_id' => [
                'required',
                'integer',
                'exists:accounts,id',
                Rule::notIn([$accountId]),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
