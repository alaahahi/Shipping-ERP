<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('land_company_cmr_files')) {
            return;
        }

        Schema::create('land_company_cmr_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('cmr_key', 80)->default('');
            $table->string('attachment_path');
            $table->string('original_name', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'cmr_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_company_cmr_files');
    }
};
