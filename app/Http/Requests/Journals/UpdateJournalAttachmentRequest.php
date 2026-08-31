<?php

namespace App\Http\Requests\Journals;

use App\Models\JournalEntry;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJournalAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $journal = $this->route('journal');

        return $journal instanceof JournalEntry
            && ($this->user()?->can('updateAttachment', $journal) ?? false);
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
