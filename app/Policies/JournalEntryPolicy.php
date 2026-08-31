<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AccountingManage->value);
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can(Permission::AccountingManage->value) && $journalEntry->isDraft();
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can(Permission::AccountingManage->value) && $journalEntry->isDraft();
    }

    public function void(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can(Permission::AccountingManage->value) && $journalEntry->isPosted();
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can(Permission::AccountingManage->value) && $journalEntry->isPosted();
    }

    public function updateMeta(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can(Permission::AccountingManage->value) && $journalEntry->isPosted();
    }

    public function updateAttachment(User $user, JournalEntry $journalEntry): bool
    {
        return $user->can(Permission::AccountingManage->value)
            && ($journalEntry->isPosted() || $journalEntry->isDraft());
    }
}
