<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CompanyWalletEntry;
use App\Models\User;

class CompanyWalletEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function view(User $user, CompanyWalletEntry $entry): bool
    {
        return $user->can(Permission::LandTripsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }

    public function update(User $user, CompanyWalletEntry $entry): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }

    public function delete(User $user, CompanyWalletEntry $entry): bool
    {
        return $user->can(Permission::LandTripsManage->value);
    }
}
