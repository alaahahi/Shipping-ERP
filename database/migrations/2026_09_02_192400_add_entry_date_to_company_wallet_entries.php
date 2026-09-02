<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_wallet_entries') || Schema::hasColumn('company_wallet_entries', 'entry_date')) {
            return;
        }

        Schema::table('company_wallet_entries', function (Blueprint $table) {
            $table->date('entry_date')->nullable()->after('currency');
            $table->index(['company_id', 'entry_date']);
        });

        DB::table('company_wallet_entries')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $date = $row->created_at
                        ? substr((string) $row->created_at, 0, 10)
                        : now()->toDateString();

                    DB::table('company_wallet_entries')
                        ->where('id', $row->id)
                        ->update(['entry_date' => $date]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('company_wallet_entries') || ! Schema::hasColumn('company_wallet_entries', 'entry_date')) {
            return;
        }

        Schema::table('company_wallet_entries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'entry_date']);
            $table->dropColumn('entry_date');
        });
    }
};
