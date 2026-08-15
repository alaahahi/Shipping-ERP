<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_cars') || ! Schema::hasColumn('land_trip_cars', 'chassis_no')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            return;
        }

        $duplicates = DB::table('land_trip_cars')
            ->whereNull('deleted_at')
            ->whereNotNull('chassis_no')
            ->where('chassis_no', '!=', '')
            ->select('chassis_no')
            ->groupBy('chassis_no')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            return;
        }

        $indexes = collect(Schema::getIndexes('land_trip_cars'));
        $exists = $indexes->contains(
            fn (array $index): bool => ($index['name'] ?? '') === 'land_trip_cars_chassis_no_unique'
        );

        if ($exists) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX land_trip_cars_chassis_no_unique ON land_trip_cars (chassis_no) WHERE deleted_at IS NULL AND chassis_no IS NOT NULL AND chassis_no != \'\''
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_trip_cars')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS land_trip_cars_chassis_no_unique');
    }
};
