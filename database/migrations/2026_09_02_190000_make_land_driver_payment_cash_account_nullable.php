<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_driver_payments') || ! Schema::hasColumn('land_driver_payments', 'cash_account_id')) {
            return;
        }

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->dropForeign(['cash_account_id']);
        });

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable()->change();
        });

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->foreign('cash_account_id')->references('id')->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_driver_payments') || ! Schema::hasColumn('land_driver_payments', 'cash_account_id')) {
            return;
        }

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->dropForeign(['cash_account_id']);
        });

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->foreignId('cash_account_id')->nullable(false)->change();
        });

        Schema::table('land_driver_payments', function (Blueprint $table) {
            $table->foreign('cash_account_id')->references('id')->on('accounts')->restrictOnDelete();
        });
    }
};
