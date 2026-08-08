<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar');
            $table->string('iso_code', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'sort_order']);
        });

        $now = now();

        DB::table('countries')->insert([
            ['name' => 'United Arab Emirates', 'name_ar' => 'الإمارات', 'iso_code' => 'AE', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Iran', 'name_ar' => 'إيران', 'iso_code' => 'IR', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Iraq', 'name_ar' => 'العراق', 'iso_code' => 'IQ', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Turkey', 'name_ar' => 'تركيا', 'iso_code' => 'TR', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kuwait', 'name_ar' => 'الكويت', 'iso_code' => 'KW', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Oman', 'name_ar' => 'عُمان', 'iso_code' => 'OM', 'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Saudi Arabia', 'name_ar' => 'السعودية', 'iso_code' => 'SA', 'is_active' => true, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
