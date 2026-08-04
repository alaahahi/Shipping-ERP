<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyage_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->decimal('shipping_price_per_car', 12, 2)->default(0);
            $table->decimal('shipping_price_aed', 12, 2)->default(0);
            $table->decimal('clearance_per_car', 12, 2)->default(40);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['voyage_id', 'company_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyage_companies');
    }
};
