<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LandTripCarStatus;
use App\Models\User;

class LandTripCarStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SettingsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SettingsManage->value);
    }

    public function update(User $user, LandTripCarStatus $landTripCarStatus): bool
    {
        return $user->can(Permission::SettingsManage->value);
    }

    public function delete(User $user, LandTripCarStatus $landTripCarStatus): bool
    {
        return $user->can(Permission::SettingsManage->value);
    }
}
