<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_driver_payments')) {
            Schema::create('land_driver_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('driver_name', 180);
                $table->string('cmr_number', 80)->nullable();
                $table->unsignedInteger('cars_count');
                $table->string('type', 32);
                $table->date('payment_date');
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('USD');
                $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique('journal_entry_id');
                $table->index(['company_id', 'payment_date']);
                $table->index('driver_name');
            });
        }

        if (Schema::hasTable('company_wallet_entries') && ! Schema::hasColumn('company_wallet_entries', 'journal_entry_id')) {
            Schema::table('company_wallet_entries', function (Blueprint $table) {
                $table->foreignId('journal_entry_id')
                    ->nullable()
                    ->after('notes')
                    ->constrained('journal_entries')
                    ->nullOnDelete();

                $table->unique('journal_entry_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_wallet_entries') && Schema::hasColumn('company_wallet_entries', 'journal_entry_id')) {
            Schema::table('company_wallet_entries', function (Blueprint $table) {
                $table->dropUnique(['journal_entry_id']);
                $table->dropConstrainedForeignId('journal_entry_id');
            });
        }

        Schema::dropIfExists('land_driver_payments');
    }
};
