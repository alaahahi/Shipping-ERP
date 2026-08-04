<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SettingsView->value);
    }

    public function manage(User $user): bool
    {
        return $user->can(Permission::SettingsManage->value);
    }
}
