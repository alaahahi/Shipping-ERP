<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('account_id')
                ->constrained('companies')
                ->nullOnDelete();
            $table->foreignId('voyage_id')
                ->nullable()
                ->after('company_id')
                ->constrained('voyages')
                ->nullOnDelete();

            $table->index(['company_id', 'account_id']);
            $table->index('voyage_id');
        });

        Schema::table('money_vouchers', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('payment_account_id')
                ->constrained('companies')
                ->nullOnDelete();

            $table->index('company_id');
        });

        Schema::create('money_voucher_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('money_voucher_id')->constrained('money_vouchers')->cascadeOnDelete();
            $table->foreignId('voyage_id')->constrained('voyages')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['money_voucher_id', 'voyage_id']);
            $table->index('voyage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_voucher_allocations');

        Schema::table('money_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voyage_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
