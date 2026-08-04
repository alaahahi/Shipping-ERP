<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number', 40)->unique();
            $table->string('type', 20);
            $table->date('voucher_date');
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 14, 2);
            $table->foreignId('payment_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('voyage_id')->nullable()->constrained('voyages')->nullOnDelete();
            $table->string('counterparty')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('journal_entry_id');
            $table->index(['type', 'status']);
            $table->index(['voucher_date', 'currency']);
            $table->index('voyage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_vouchers');
    }
};
