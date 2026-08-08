<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iran_cars', function (Blueprint $table) {
            $table->string('sale_state', 20)->default('unsold')->after('status');
            $table->decimal('sale_price', 14, 2)->nullable()->after('total_amount');
            $table->date('sold_at')->nullable()->after('sale_state');
            $table->foreignId('sold_by')->nullable()->after('sold_at')->constrained('users')->nullOnDelete();
            $table->index('sale_state');
        });

        if (! Schema::hasTable('iran_car_payments')) {
            return;
        }

        $soldIds = DB::table('iran_cars')
            ->where(function ($query): void {
                $query->whereNotNull('invoice_journal_id')
                    ->orWhereIn('id', DB::table('iran_car_payments')->select('iran_car_id'));
            })
            ->pluck('id');

        if ($soldIds->isEmpty()) {
            return;
        }

        DB::table('iran_cars')
            ->whereIn('id', $soldIds)
            ->update([
                'sale_state' => 'sold',
                'sale_price' => DB::raw('total_amount'),
                'sold_at' => DB::raw('DATE(created_at)'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('iran_cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sold_by');
            $table->dropIndex(['sale_state']);
            $table->dropColumn(['sale_state', 'sale_price', 'sold_at']);
        });
    }
};
