<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_cars')) {
            return;
        }

        Schema::table('land_trip_cars', function (Blueprint $table) {
            if (! Schema::hasColumn('land_trip_cars', 'model')) {
                $table->string('model', 180)->nullable()->after('consignee_name');
            }
            if (! Schema::hasColumn('land_trip_cars', 'color')) {
                $table->string('color', 80)->nullable()->after('model');
            }
        });

        if (Schema::hasColumn('land_trip_cars', 'model') && Schema::hasColumn('land_trip_cars', 'description')) {
            DB::table('land_trip_cars')
                ->whereNull('model')
                ->whereNotNull('description')
                ->where('description', '!=', '')
                ->update(['model' => DB::raw('description')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_trip_cars')) {
            return;
        }

        Schema::table('land_trip_cars', function (Blueprint $table) {
            if (Schema::hasColumn('land_trip_cars', 'color')) {
                $table->dropColumn('color');
            }
            if (Schema::hasColumn('land_trip_cars', 'model')) {
                $table->dropColumn('model');
            }
        });
    }
};
