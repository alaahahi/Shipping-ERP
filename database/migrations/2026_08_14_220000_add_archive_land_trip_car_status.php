<?php

use App\Services\LandTripCarStatusService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('land_trip_car_statuses', 'is_archive')) {
            Schema::table('land_trip_car_statuses', function (Blueprint $table) {
                $table->boolean('is_archive')->default(false)->after('is_active');
                $table->index('is_archive');
            });
        }

        app(LandTripCarStatusService::class)->upsertDefaults();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('land_trip_car_statuses', 'is_archive')) {
            return;
        }

        Schema::table('land_trip_car_statuses', function (Blueprint $table) {
            $table->dropIndex(['is_archive']);
            $table->dropColumn('is_archive');
        });
    }
};
