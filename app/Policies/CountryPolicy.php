<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Country;
use App\Models\User;

class CountryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SettingsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SettingsManage->value);
    }

    public function update(User $user, Country $country): bool
    {
        return $user->can(Permission::SettingsManage->value);
    }

    public function delete(User $user, Country $country): bool
    {
        return $user->can(Permission::SettingsManage->value);
    }
}
