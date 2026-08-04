<?php

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $parent = Account::query()->where('code', '5000')->first();

        Account::query()->updateOrCreate(
            ['code' => '5110'],
            [
                'name' => 'Ship Expenses USD',
                'type' => AccountType::Expense,
                'currency' => Currency::USD,
                'parent_id' => $parent?->id,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        Account::query()->where('code', '5110')->where('is_system', true)->delete();
    }
};
