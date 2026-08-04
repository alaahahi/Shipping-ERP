<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Voyage;

class VoyagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function view(User $user, Voyage $voyage): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::VoyagesManage->value);
    }

    public function update(User $user, Voyage $voyage): bool
    {
        return $user->can(Permission::VoyagesManage->value) && $voyage->isEditable();
    }

    public function delete(User $user, Voyage $voyage): bool
    {
        return $user->can(Permission::VoyagesManage->value) && $voyage->isEditable();
    }

    public function transition(User $user, Voyage $voyage): bool
    {
        return $user->can(Permission::VoyagesManage->value);
    }

    public function postRevenue(User $user, Voyage $voyage): bool
    {
        return $user->can(Permission::VoyagesManage->value)
            && $user->can(Permission::AccountingManage->value);
    }

    public function postCommission(User $user, Voyage $voyage): bool
    {
        return $user->can(Permission::VoyagesManage->value)
            && $user->can(Permission::AccountingManage->value);
    }
}
