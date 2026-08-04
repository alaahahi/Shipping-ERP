<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained('ships')->restrictOnDelete();
            $table->string('voyage_number', 50);
            $table->date('sailing_date');
            $table->date('arrival_date')->nullable();
            $table->string('pol', 120)->nullable();
            $table->string('pod', 120)->nullable();
            $table->string('captain')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('cost_per_car_aed', 12, 2)->default(0);
            $table->decimal('captain_commission_aed', 12, 2)->default(0);
            $table->decimal('purchase_price_aed', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ship_id', 'voyage_number']);
            $table->index(['status', 'sailing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyages');
    }
};
