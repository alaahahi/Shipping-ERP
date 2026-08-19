<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('land_payment_chassis')) {
            return;
        }

        Schema::create('land_payment_chassis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->foreignId('land_trip_car_id')->nullable()->constrained('land_trip_cars')->nullOnDelete();
            $table->string('chassis_no', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payable_type', 'payable_id'], 'land_payment_chassis_payable_idx');
            $table->index(['company_id', 'id']);
            $table->index('land_trip_car_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_payment_chassis');
    }
};
