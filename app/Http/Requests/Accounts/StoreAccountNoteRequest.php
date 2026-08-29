<?php

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account
            && ($this->user()?->can('update', $account) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'note_date' => ['nullable', 'date'],
        ];
    }
}
