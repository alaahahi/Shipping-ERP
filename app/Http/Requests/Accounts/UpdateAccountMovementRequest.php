<?php

namespace App\Http\Requests\Accounts;

use App\Enums\Permission;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountMovementRequest extends FormRequest
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
        return [
            'description' => ['required', 'string', 'max:255'],
            'attachment' => AttachmentRules::file(),
            'remove_attachment' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('remove_attachment')) {
            $this->merge([
                'remove_attachment' => filter_var($this->input('remove_attachment'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
