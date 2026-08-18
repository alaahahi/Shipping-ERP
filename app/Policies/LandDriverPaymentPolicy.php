<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LandDriverPayment;
use App\Models\User;

class LandDriverPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function view(User $user, LandDriverPayment $payment): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }

    public function delete(User $user, LandDriverPayment $payment): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }
}
