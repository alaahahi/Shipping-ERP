<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class LandTripCarDeletionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function restore(User $user): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }
}
