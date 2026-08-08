<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ship_expenses', function (Blueprint $table) {
            $table->foreignId('paid_by_owner_id')
                ->nullable()
                ->after('created_by')
                ->constrained('owners')
                ->nullOnDelete();

            $table->index(['ship_id', 'paid_by_owner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ship_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by_owner_id');
        });
    }
};
