<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionService::class)->seedDefaults();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@shipping.local'],
            [
                'name' => 'System Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles([SystemRole::Admin->value]);
    }
}
