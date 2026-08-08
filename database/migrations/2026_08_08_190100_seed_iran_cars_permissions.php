<?php

use App\Services\RolePermissionService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RolePermissionService::class)->seedDefaults();
    }

    public function down(): void
    {
        // Permissions remain; role map can be re-seeded later.
    }
};
