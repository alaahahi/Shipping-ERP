<?php

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ship_partner_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained('ships')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('owners')->restrictOnDelete();
            $table->date('contribution_date');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('description')->nullable();
            $table->string('reference', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ship_id', 'contribution_date']);
            $table->index(['ship_id', 'owner_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('voyage_id')
                ->constrained('owners')
                ->nullOnDelete();
        });

        $liability = Account::query()->where('code', '2000')->first();

        Account::query()->updateOrCreate(
            ['code' => '2210'],
            [
                'name' => 'Ship Partner Clearing',
                'type' => AccountType::Liability->value,
                'currency' => Currency::USD->value,
                'parent_id' => $liability?->id,
                'is_system' => true,
                'is_active' => true,
            ]
        );

        Account::query()->updateOrCreate(
            ['code' => '2215'],
            [
                'name' => 'Ship Partner Clearing AED',
                'type' => AccountType::Liability->value,
                'currency' => Currency::AED->value,
                'parent_id' => $liability?->id,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::dropIfExists('ship_partner_contributions');
    }
};
