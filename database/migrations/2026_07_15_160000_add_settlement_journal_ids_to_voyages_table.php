<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyages', function (Blueprint $table): void {
            $table->foreignId('revenue_journal_entry_id')
                ->nullable()
                ->after('notes')
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->foreignId('commission_journal_entry_id')
                ->nullable()
                ->after('revenue_journal_entry_id')
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->unique('revenue_journal_entry_id');
            $table->unique('commission_journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('voyages', function (Blueprint $table): void {
            $table->dropUnique(['revenue_journal_entry_id']);
            $table->dropUnique(['commission_journal_entry_id']);
            $table->dropConstrainedForeignId('revenue_journal_entry_id');
            $table->dropConstrainedForeignId('commission_journal_entry_id');
        });
    }
};
