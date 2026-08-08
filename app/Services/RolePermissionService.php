<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\SystemRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionService
{
    /**
     * Seed all system permissions and default role mappings.
     */
    public function seedDefaults(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (Permission::cases() as $permission) {
                PermissionModel::findOrCreate($permission->value, 'web');
            }

            foreach ($this->rolePermissionMap() as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($permissions);
            }
        });
    }

    /**
     * Sync permissions for an existing role.
     *
     * @param  list<string>  $permissionNames
     */
    public function syncRolePermissions(Role $role, array $permissionNames): Role
    {
        return DB::transaction(function () use ($role, $permissionNames): Role {
            $role->syncPermissions($permissionNames);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            return $role->fresh('permissions');
        });
    }

    /**
     * @return array<string, list<string>>
     */
    public function rolePermissionMap(): array
    {
        $all = array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases()
        );

        $viewOnly = [
            Permission::ShipsView->value,
            Permission::VoyagesView->value,
            Permission::CarsView->value,
            Permission::AccountingView->value,
            Permission::ReportsView->value,
            Permission::SettingsView->value,
            Permission::DubaiAccountsView->value,
            Permission::LandTripsView->value,
            Permission::IranCarsView->value,
        ];

        return [
            SystemRole::Admin->value => $all,
            SystemRole::Accountant->value => [
                Permission::ShipsView->value,
                Permission::VoyagesView->value,
                Permission::CarsView->value,
                Permission::AccountingView->value,
                Permission::AccountingManage->value,
                Permission::ReportsView->value,
                Permission::DubaiAccountsView->value,
                Permission::DubaiAccountsManage->value,
                Permission::LandTripsView->value,
                Permission::IranCarsView->value,
                Permission::IranCarsManage->value,
            ],
            SystemRole::Operator->value => [
                Permission::ShipsView->value,
                Permission::ShipsManage->value,
                Permission::VoyagesView->value,
                Permission::VoyagesManage->value,
                Permission::CarsView->value,
                Permission::CarsManage->value,
                Permission::ExcelImport->value,
                Permission::ReportsView->value,
                Permission::DubaiAccountsView->value,
                Permission::DubaiAccountsManage->value,
                Permission::LandTripsView->value,
                Permission::LandTripsManage->value,
                Permission::IranCarsView->value,
                Permission::IranCarsManage->value,
            ],
            SystemRole::Viewer->value => $viewOnly,
        ];
    }

    /**
     * Group permissions for UI forms.
     *
     * @return array<string, list<array{name: string, label: string}>>
     */
    public function groupedPermissions(): array
    {
        $grouped = [];

        foreach (Permission::cases() as $permission) {
            $grouped[$permission->group()][] = [
                'name' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        return $grouped;
    }
}
