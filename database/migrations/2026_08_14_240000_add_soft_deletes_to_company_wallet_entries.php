<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_wallet_entries') || Schema::hasColumn('company_wallet_entries', 'deleted_at')) {
            return;
        }

        Schema::table('company_wallet_entries', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('company_wallet_entries') || ! Schema::hasColumn('company_wallet_entries', 'deleted_at')) {
            return;
        }

        Schema::table('company_wallet_entries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
