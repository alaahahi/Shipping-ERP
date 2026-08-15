<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_cars') || Schema::hasColumn('land_trip_cars', 'price')) {
            return;
        }

        Schema::table('land_trip_cars', function (Blueprint $table) {
            $table->decimal('price', 14, 2)->default(0)->after('weight');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_trip_cars') || ! Schema::hasColumn('land_trip_cars', 'price')) {
            return;
        }

        Schema::table('land_trip_cars', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
