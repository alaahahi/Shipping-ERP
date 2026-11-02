<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_driver_payments') || Schema::hasColumn('land_driver_payments', 'notes')) {
            return;
        }

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('cmr_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_driver_payments') || ! Schema::hasColumn('land_driver_payments', 'notes')) {
            return;
        }

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
