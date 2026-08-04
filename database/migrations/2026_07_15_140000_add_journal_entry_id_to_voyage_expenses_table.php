<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyage_expenses', function (Blueprint $table): void {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('created_by')
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->unique('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('voyage_expenses', function (Blueprint $table): void {
            $table->dropUnique(['journal_entry_id']);
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};
