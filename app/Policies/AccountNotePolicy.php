<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\AccountNote;
use App\Models\User;

class AccountNotePolicy
{
    public function viewAny(User $user, Account $account): bool
    {
        return $user->can('view', $account);
    }

    public function create(User $user, Account $account): bool
    {
        return $user->can('update', $account);
    }

    public function update(User $user, AccountNote $note): bool
    {
        return $user->can('update', $note->account);
    }

    public function delete(User $user, AccountNote $note): bool
    {
        return $user->can('update', $note->account);
    }
}
