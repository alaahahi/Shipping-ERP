<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ship;
use App\Models\ShipOwnership;
use App\Models\User;

class ShipOwnershipPolicy
{
    public function create(User $user, ?Ship $ship = null): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }

    public function update(User $user, ShipOwnership $ownership): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }

    public function delete(User $user, ShipOwnership $ownership): bool
    {
        return $user->can(Permission::ShipsManage->value);
    }
}
