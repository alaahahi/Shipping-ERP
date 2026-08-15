<?php

use App\Enums\LandTripCarRowTone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('land_trip_car_statuses', 'color')) {
            Schema::table('land_trip_car_statuses', function (Blueprint $table) {
                $table->string('color', 7)->default('#64748B')->after('row_tone');
            });
        }

        DB::table('land_trip_car_statuses')
            ->where('row_tone', LandTripCarRowTone::Yellow->value)
            ->update(['color' => '#F59E0B']);

        DB::table('land_trip_car_statuses')
            ->where('row_tone', LandTripCarRowTone::Green->value)
            ->update(['color' => '#16A34A']);

        DB::table('land_trip_car_statuses')
            ->where('row_tone', LandTripCarRowTone::Neutral->value)
            ->update(['color' => '#64748B']);

        DB::table('land_trip_car_statuses')
            ->where('code', 'trip_to_bukhara')
            ->update(['color' => '#EAB308']);

        DB::table('land_trip_car_statuses')
            ->where('code', 'loaded_in_bukhara')
            ->update(['color' => '#F97316']);

        DB::table('land_trip_car_statuses')
            ->where('code', 'trip_to_iran_bazargan')
            ->update(['color' => '#22C55E']);

        DB::table('land_trip_car_statuses')
            ->where('code', 'from_iran_to_erbil')
            ->update(['color' => '#0D9488']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('land_trip_car_statuses', 'color')) {
            Schema::table('land_trip_car_statuses', function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
