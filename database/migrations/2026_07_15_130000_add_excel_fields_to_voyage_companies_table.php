<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyage_companies', function (Blueprint $table) {
            $table->string('excel_file_path')->nullable()->after('notes');
            $table->string('excel_original_name')->nullable()->after('excel_file_path');
            $table->timestamp('excel_uploaded_at')->nullable()->after('excel_original_name');
            $table->unsignedInteger('excel_imported_count')->default(0)->after('excel_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('voyage_companies', function (Blueprint $table) {
            $table->dropColumn([
                'excel_file_path',
                'excel_original_name',
                'excel_uploaded_at',
                'excel_imported_count',
            ]);
        });
    }
};
