<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_trip_car_price_changes')) {
            Schema::create('land_trip_car_price_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('batch_uuid', 36)->nullable();
                $table->unsignedInteger('cars_count')->default(0);
                $table->decimal('new_price', 12, 2);
                $table->timestamps();

                $table->index(['company_id', 'id']);
                $table->index(['batch_uuid']);
            });
        }

        if (! Schema::hasTable('land_trip_car_price_change_items')) {
            Schema::create('land_trip_car_price_change_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('land_trip_car_price_change_id')
                    ->constrained('land_trip_car_price_changes')
                    ->cascadeOnDelete();
                $table->foreignId('land_trip_car_id')->nullable()->constrained('land_trip_cars')->nullOnDelete();
                $table->string('chassis_no', 64)->nullable();
                $table->decimal('old_price', 12, 2);
                $table->decimal('new_price', 12, 2);
                $table->timestamps();

                $table->index('land_trip_car_price_change_id', 'lt_car_price_change_items_change_idx');
                $table->index('land_trip_car_id', 'lt_car_price_change_items_car_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('land_trip_car_price_change_items');
        Schema::dropIfExists('land_trip_car_price_changes');
    }
};
