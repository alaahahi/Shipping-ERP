<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VoyageCompany;

class VoyageCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function view(User $user, VoyageCompany $company): bool
    {
        return $user->can(Permission::VoyagesView->value);
    }

    public function create(User $user, ?Voyage $voyage = null): bool
    {
        return $user->can(Permission::VoyagesManage->value)
            && ($voyage === null || $voyage->isEditable());
    }

    public function update(User $user, VoyageCompany $company): bool
    {
        return $user->can(Permission::VoyagesManage->value)
            && $company->voyage?->isEditable();
    }

    public function delete(User $user, VoyageCompany $company): bool
    {
        return $user->can(Permission::VoyagesManage->value)
            && $company->voyage?->isEditable();
    }
}
