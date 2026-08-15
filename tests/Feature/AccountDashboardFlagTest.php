<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountDashboardFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_flagged_account_appears_on_dashboard(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $cash->update(['show_on_dashboard' => true]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('pinnedAccounts', 1)
                ->where('pinnedAccounts.0.code', '1100')
            );
    }

    public function test_inactive_flagged_account_is_hidden_from_dashboard(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $cash->update([
            'show_on_dashboard' => true,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('pinnedAccounts', 0)
            );
    }

    public function test_manager_can_toggle_dashboard_flag(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)
            ->from(route('accounts.show', $cash))
            ->post(route('accounts.dashboard.toggle', $cash))
            ->assertRedirect(route('accounts.show', $cash));

        $this->assertTrue($cash->fresh()->show_on_dashboard);
    }

    public function test_viewer_cannot_toggle_dashboard_flag(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::AccountingView->value);
        $cash = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.dashboard.toggle', $cash))
            ->assertForbidden();
    }

    private function accountingUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::AccountingView->value,
            Permission::AccountingManage->value,
        ]);

        return $user;
    }
}
