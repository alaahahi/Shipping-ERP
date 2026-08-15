<?php

use App\Services\LandTripCarStatusService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::table('land_trip_cars', function (Blueprint $table) {
            $table->foreignId('location_status_id')
                ->nullable()
                ->after('land_trip_id')
                ->constrained('land_trip_car_statuses')
                ->nullOnDelete();
            $table->string('cmr_waybill', 80)->nullable()->after('chassis_no');
            $table->unsignedInteger('sort_order')->default(0)->after('notes');
            $table->index('location_status_id');
        });

        app(LandTripCarStatusService::class)->seedDefaults();
    }

    public function down(): void
    {
        Schema::table('land_trip_cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_status_id');
            $table->dropColumn(['cmr_waybill', 'sort_order']);
        });

        Schema::dropIfExists('land_trip_car_statuses');
    }
};
