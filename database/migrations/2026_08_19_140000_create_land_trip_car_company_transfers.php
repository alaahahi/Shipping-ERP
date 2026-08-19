<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_car_company_transfers')) {
            Schema::create('land_trip_car_company_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('from_company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('to_company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('cars_count')->default(0);
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                $table->index(['from_company_id', 'id']);
                $table->index(['to_company_id', 'id']);
            });
        }

        if (! Schema::hasTable('land_trip_car_company_transfer_items')) {
            Schema::create('land_trip_car_company_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('land_trip_car_company_transfer_id')
                    ->constrained('land_trip_car_company_transfers')
                    ->cascadeOnDelete();
                $table->foreignId('land_trip_car_id')->nullable()->constrained('land_trip_cars')->nullOnDelete();
                $table->string('chassis_no', 64)->nullable();
                $table->foreignId('from_land_trip_id')->nullable()->constrained('land_trips')->nullOnDelete();
                $table->foreignId('to_land_trip_id')->nullable()->constrained('land_trips')->nullOnDelete();
                $table->string('cmr_waybill', 80)->nullable();
                $table->timestamps();

                $table->index('land_trip_car_company_transfer_id', 'lt_car_co_transfer_items_transfer_idx');
                $table->index('land_trip_car_id', 'lt_car_co_transfer_items_car_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('land_trip_car_company_transfer_items');
        Schema::dropIfExists('land_trip_car_company_transfers');
    }
};
