<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DubaiPartner;
use App\Models\User;

class DubaiPartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DubaiAccountsView->value);
    }

    public function view(User $user, DubaiPartner $dubaiPartner): bool
    {
        return $user->can(Permission::DubaiAccountsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DubaiAccountsManage->value);
    }

    public function update(User $user, DubaiPartner $dubaiPartner): bool
    {
        return $user->can(Permission::DubaiAccountsManage->value);
    }

    public function delete(User $user, DubaiPartner $dubaiPartner): bool
    {
        return $user->can(Permission::DubaiAccountsManage->value);
    }
}
