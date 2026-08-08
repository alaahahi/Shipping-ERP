<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LandTrip;
use App\Models\User;

class LandTripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function view(User $user, LandTrip $landTrip): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }

    public function update(User $user, LandTrip $landTrip): bool
    {
        return $user->can(Permission::LandTripsManage->value) && $landTrip->isEditable();
    }

    public function delete(User $user, LandTrip $landTrip): bool
    {
        return $user->can(Permission::LandTripsManage->value) && $landTrip->isEditable();
    }

    public function transition(User $user, LandTrip $landTrip): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }

    public function post(User $user, LandTrip $landTrip): bool
    {
        return $user->can(Permission::AccountingManage->value)
            && $user->can(Permission::LandTripsView->value);
    }
}
