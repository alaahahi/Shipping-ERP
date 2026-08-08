<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('land_trips', function (Blueprint $table) {
            $table->id();
            $table->string('cmr_number');
            $table->string('driver_name');
            $table->foreignId('from_country_id')->constrained('countries')->restrictOnDelete();
            $table->foreignId('to_country_id')->constrained('countries')->restrictOnDelete();
            $table->date('departure_date');
            $table->date('arrival_date')->nullable();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->decimal('freight_amount', 14, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->string('status', 20)->default('draft');
            $table->foreignId('voyage_id')->nullable()->constrained('voyages')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('cmr_number');
            $table->index(['status', 'departure_date']);
            $table->index('company_id');
        });

        Schema::create('land_trip_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('land_trip_id')->constrained('land_trips')->cascadeOnDelete();
            $table->foreignId('voyage_car_id')->nullable()->constrained('voyage_cars')->nullOnDelete();
            $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->string('chassis_no', 64)->nullable();
            $table->string('consignee_name');
            $table->string('description')->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['land_trip_id', 'chassis_no']);
            $table->index('voyage_car_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_trip_cars');
        Schema::dropIfExists('land_trips');
    }
};
