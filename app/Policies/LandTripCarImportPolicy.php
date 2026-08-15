<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LandTripCarImport;
use App\Models\User;

class LandTripCarImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function undo(User $user, ?LandTripCarImport $import = null): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }
}
