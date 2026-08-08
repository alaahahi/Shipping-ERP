<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\IranCar;
use App\Models\User;

class IranCarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::IranCarsView->value);
    }

    public function view(User $user, IranCar $iranCar): bool
    {
        return $user->can(Permission::IranCarsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::IranCarsManage->value);
    }

    public function update(User $user, IranCar $iranCar): bool
    {
        return $user->can(Permission::IranCarsManage->value) && ! $iranCar->isCancelled();
    }

    public function delete(User $user, IranCar $iranCar): bool
    {
        return $user->can(Permission::IranCarsManage->value) && ! $iranCar->hasPayments();
    }
}
