<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('show_on_dashboard')->default(false)->after('is_active');
            $table->index('show_on_dashboard');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['show_on_dashboard']);
            $table->dropColumn('show_on_dashboard');
        });
    }
};
