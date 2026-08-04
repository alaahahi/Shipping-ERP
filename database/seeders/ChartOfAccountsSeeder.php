<?php

namespace Database\Seeders;

use App\Services\AccountService;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        app(AccountService::class)->seedChartOfAccounts();
    }
}
