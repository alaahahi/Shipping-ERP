<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function view(User $user, Account $account): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AccountingManage->value);
    }

    public function update(User $user, Account $account): bool
    {
        return $user->can(Permission::AccountingManage->value);
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->can(Permission::AccountingManage->value) && ! $account->is_system;
    }
}
