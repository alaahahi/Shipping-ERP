<?php

use App\Services\LandTripCarStatusService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_car_statuses')) {
            Schema::create('land_trip_car_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->string('name_ckb')->nullable();
                $table->string('row_tone', 20)->default('neutral');
                $table->json('match_aliases')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        if (Schema::hasTable('land_trip_cars') && ! Schema::hasColumn('land_trip_cars', 'location_status_id')) {
            Schema::table('land_trip_cars', function (Blueprint $table) {
                $table->foreignId('location_status_id')
                    ->nullable()
                    ->constrained('land_trip_car_statuses')
                    ->nullOnDelete();
                $table->index('location_status_id');
            });
        }

        if (Schema::hasTable('land_trip_cars') && ! Schema::hasColumn('land_trip_cars', 'cmr_waybill')) {
            Schema::table('land_trip_cars', function (Blueprint $table) {
                $table->string('cmr_waybill', 80)->nullable();
            });
        }

        if (Schema::hasTable('land_trip_cars') && ! Schema::hasColumn('land_trip_cars', 'sort_order')) {
            Schema::table('land_trip_cars', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0);
            });
        }

        app(LandTripCarStatusService::class)->seedDefaults();
    }

    public function down(): void
    {
        if (Schema::hasTable('land_trip_cars') && Schema::hasColumn('land_trip_cars', 'location_status_id')) {
            Schema::table('land_trip_cars', function (Blueprint $table) {
                $table->dropConstrainedForeignId('location_status_id');
            });
        }

        if (Schema::hasTable('land_trip_cars')) {
            Schema::table('land_trip_cars', function (Blueprint $table) {
                $columns = array_values(array_filter(
                    ['cmr_waybill', 'sort_order'],
                    fn (string $column) => Schema::hasColumn('land_trip_cars', $column)
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('land_trip_car_statuses');
    }
};
