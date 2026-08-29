<?php

namespace App\Http\Requests\Accounts;

use App\Models\AccountNote;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');

        return $note instanceof AccountNote
            && ($this->user()?->can('update', $note) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
