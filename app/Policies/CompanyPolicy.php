<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::VoyagesManage->value);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can(Permission::VoyagesManage->value);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can(Permission::VoyagesManage->value)
            && $company->voyageCompanies()->doesntExist();
    }
}
