<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyage_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages')->cascadeOnDelete();
            $table->foreignId('voyage_company_id')->constrained('voyage_companies')->cascadeOnDelete();
            $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->string('chassis_no', 64)->nullable();
            $table->string('consignee_name');
            $table->string('shipper_name')->nullable();
            $table->string('description')->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->string('code', 80)->nullable();
            $table->unsignedInteger('row_number')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['voyage_id', 'voyage_company_id']);
            $table->index(['voyage_id', 'chassis_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyage_cars');
    }
};
