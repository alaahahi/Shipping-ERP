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

        Schema::table('land_trip_cars', function (Blueprint $table) {
            if (! Schema::hasColumn('land_trip_cars', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->after('color');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_trip_cars')) {
            return;
        }

        Schema::table('land_trip_cars', function (Blueprint $table) {
            if (Schema::hasColumn('land_trip_cars', 'year')) {
                $table->dropColumn('year');
            }
        });
    }
};
