<?php

namespace App\Http\Requests\Accounts;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountMovementAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');
        $journal = $this->route('journal');

        return $account instanceof Account
            && $journal instanceof JournalEntry
            && ($this->user()?->can('updateMeta', $journal) ?? false)
            && ($this->user()?->can(Permission::AccountingManage->value) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachment' => AttachmentRules::requiredFile(),
        ];
    }
}
