<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VoyageCar;

class VoyageCarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoyagesView->value) || $user->can(Permission::CarsView->value);
    }

    public function view(User $user, VoyageCar $voyageCar): bool
    {
        return $user->can(Permission::VoyagesView->value) || $user->can(Permission::CarsView->value);
    }

    public function create(User $user, ?Voyage $voyage = null): bool
    {
        $canManage = $user->can(Permission::VoyagesManage->value) || $user->can(Permission::CarsManage->value);

        return $canManage && ($voyage === null || $voyage->isEditable());
    }

    public function update(User $user, VoyageCar $voyageCar): bool
    {
        $canManage = $user->can(Permission::VoyagesManage->value) || $user->can(Permission::CarsManage->value);

        return $canManage && $voyageCar->voyage?->isEditable();
    }

    public function delete(User $user, VoyageCar $voyageCar): bool
    {
        $canManage = $user->can(Permission::VoyagesManage->value) || $user->can(Permission::CarsManage->value);

        return $canManage && $voyageCar->voyage?->isEditable();
    }
}
