<?php

namespace App\Support;

final class AttachmentRules
{
    /**
     * Shared upload rules for the generic attachments table.
     *
     * @return list<string>
     */
    public static function file(): array
    {
        return ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'];
    }
}
