<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_wallet_entries') && ! Schema::hasColumn('company_wallet_entries', 'attachment_path')) {
            Schema::table('company_wallet_entries', function (Blueprint $table) {
                $table->string('attachment_path')->nullable()->after('notes');
                $table->string('attachment_original_name', 180)->nullable()->after('attachment_path');
            });
        }

        if (Schema::hasTable('land_driver_payments') && ! Schema::hasColumn('land_driver_payments', 'attachment_path')) {
            Schema::table('land_driver_payments', function (Blueprint $table) {
                $table->string('attachment_path')->nullable()->after('created_by');
                $table->string('attachment_original_name', 180)->nullable()->after('attachment_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_wallet_entries') && Schema::hasColumn('company_wallet_entries', 'attachment_path')) {
            Schema::table('company_wallet_entries', function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'attachment_original_name']);
            });
        }

        if (Schema::hasTable('land_driver_payments') && Schema::hasColumn('land_driver_payments', 'attachment_path')) {
            Schema::table('land_driver_payments', function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'attachment_original_name']);
            });
        }
    }
};
