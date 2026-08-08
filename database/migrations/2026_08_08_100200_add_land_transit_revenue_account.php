<?php

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $parent = Account::query()->where('code', '4000')->first();

        Account::query()->updateOrCreate(
            ['code' => '4200'],
            [
                'name' => 'Land Transit Revenue',
                'type' => AccountType::Revenue->value,
                'currency' => Currency::USD->value,
                'parent_id' => $parent?->id,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        Account::query()
            ->where('code', '4200')
            ->whereDoesntHave('journalLines')
            ->delete();
    }
};
