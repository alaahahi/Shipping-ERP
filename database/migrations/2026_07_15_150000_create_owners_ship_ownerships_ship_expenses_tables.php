<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('national_id', 80)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('ship_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained('ships')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
            $table->decimal('share_percent', 5, 2);
            $table->boolean('is_managing')->default(false);
            $table->date('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ship_id', 'owner_id']);
            $table->index(['ship_id', 'share_percent']);
        });

        Schema::create('ship_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained('ships')->cascadeOnDelete();
            $table->string('expense_type', 30);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('expense_date');
            $table->string('vendor')->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('journal_entry_id');
            $table->index(['ship_id', 'expense_date']);
            $table->index(['ship_id', 'expense_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ship_expenses');
        Schema::dropIfExists('ship_ownerships');
        Schema::dropIfExists('owners');
    }
};
