<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_cars')) {
            return;
        }

        if (! Schema::hasColumn('land_trip_cars', 'deleted_at')) {
            Schema::table('land_trip_cars', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        $indexes = collect(Schema::getIndexes('land_trip_cars'));
        $unique = $indexes->first(function (array $index): bool {
            $columns = $index['columns'] ?? [];

            return ($index['unique'] ?? false)
                && in_array('land_trip_id', $columns, true)
                && in_array('chassis_no', $columns, true)
                && count($columns) === 2;
        });
        $uniqueName = is_array($unique) ? ($unique['name'] ?? null) : null;

        if ($uniqueName) {
            Schema::table('land_trip_cars', function (Blueprint $table) use ($uniqueName) {
                $table->dropUnique($uniqueName);
            });
        }

        $indexes = collect(Schema::getIndexes('land_trip_cars'));
        $hasChassisIndex = $indexes->contains(function (array $index): bool {
            $columns = $index['columns'] ?? [];

            return in_array('land_trip_id', $columns, true)
                && in_array('chassis_no', $columns, true);
        });

        if (! $hasChassisIndex) {
            Schema::table('land_trip_cars', function (Blueprint $table) {
                $table->index(['land_trip_id', 'chassis_no']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_trip_cars') || ! Schema::hasColumn('land_trip_cars', 'deleted_at')) {
            return;
        }

        Schema::table('land_trip_cars', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
