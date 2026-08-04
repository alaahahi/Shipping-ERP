<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyage_waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages')->cascadeOnDelete();
            $table->string('name');
            $table->dateTime('reached_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['voyage_id', 'sort_order']);
        });

        Schema::create('voyage_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages')->cascadeOnDelete();
            $table->string('route_type', 20)->default('sea'); // sea | road
            $table->json('coordinates'); // [{lat, lng}, ...]
            $table->string('color', 7)->default('#0d9488');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['voyage_id', 'route_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyage_routes');
        Schema::dropIfExists('voyage_waypoints');
    }
};
