<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyage_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages')->cascadeOnDelete();
            $table->string('expense_type', 30);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('expense_date');
            $table->string('vendor')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['voyage_id', 'expense_date']);
            $table->index(['voyage_id', 'expense_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyage_expenses');
    }
};
