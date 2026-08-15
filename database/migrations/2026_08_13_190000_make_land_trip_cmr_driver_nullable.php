<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('land_trips')->where('cmr_number', '')->update(['cmr_number' => null]);
        DB::table('land_trips')->where('driver_name', '')->update(['driver_name' => null]);

        Schema::table('land_trips', function (Blueprint $table) {
            $table->string('cmr_number')->nullable()->change();
            $table->string('driver_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('land_trips')->whereNull('cmr_number')->update([
            'cmr_number' => DB::raw("CONCAT('LEGACY-', id)"),
        ]);
        DB::table('land_trips')->whereNull('driver_name')->update(['driver_name' => '—']);

        Schema::table('land_trips', function (Blueprint $table) {
            $table->string('cmr_number')->nullable(false)->change();
            $table->string('driver_name')->nullable(false)->change();
        });
    }
};
