<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CompanyDirectCharge;
use App\Models\User;

class CompanyDirectChargePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function view(User $user, CompanyDirectCharge $charge): bool
    {
        return $user->can(Permission::AccountingView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AccountingManage->value);
    }
}
