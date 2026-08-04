<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ship;
use App\Models\User;

class ShipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ShipsView->value);
    }

    public function view(User $user, Ship $ship): bool
    {
        return $user->can(Permission::ShipsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }

    public function update(User $user, Ship $ship): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }

    public function delete(User $user, Ship $ship): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }
}
