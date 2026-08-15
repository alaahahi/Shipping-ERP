<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('land_trip_car_location_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_location_status_id')->nullable()->constrained('land_trip_car_statuses')->nullOnDelete();
            $table->unsignedInteger('cars_count')->default(0);
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'undone_at', 'id']);
        });

        Schema::create('land_trip_car_location_change_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('land_trip_car_location_change_id')
                ->constrained('land_trip_car_location_changes')
                ->cascadeOnDelete();
            $table->foreignId('land_trip_car_id')->nullable()->constrained('land_trip_cars')->nullOnDelete();
            $table->foreignId('from_location_status_id')->nullable()->constrained('land_trip_car_statuses')->nullOnDelete();
            $table->foreignId('to_location_status_id')->nullable()->constrained('land_trip_car_statuses')->nullOnDelete();
            $table->string('chassis_no', 64)->nullable();
            $table->timestamps();

            $table->index('land_trip_car_location_change_id', 'lt_car_loc_change_items_change_idx');
            $table->index('land_trip_car_id', 'lt_car_loc_change_items_car_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_trip_car_location_change_items');
        Schema::dropIfExists('land_trip_car_location_changes');
    }
};
