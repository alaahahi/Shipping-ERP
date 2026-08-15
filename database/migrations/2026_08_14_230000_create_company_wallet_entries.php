<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('voucher_number', 32)->unique();
            $table->string('type', 16);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'currency', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_wallet_entries');
    }
};
