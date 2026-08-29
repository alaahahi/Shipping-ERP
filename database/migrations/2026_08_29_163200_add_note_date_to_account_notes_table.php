<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_notes', function (Blueprint $table) {
            $table->date('note_date')->nullable()->after('body');
            $table->index(['account_id', 'note_date']);
        });

        foreach (DB::table('account_notes')->orderBy('id')->get() as $row) {
            DB::table('account_notes')->where('id', $row->id)->update([
                'note_date' => Carbon::parse($row->created_at)->toDateString(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('account_notes', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'note_date']);
            $table->dropColumn('note_date');
        });
    }
};
