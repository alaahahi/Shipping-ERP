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

    public function test_chart_index_ignores_page_query(): void
    {
        $this->actingAs($this->accountingUser())
            ->get(route('accounts.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Index')
                ->where('accounts.current_page', 1)
            );
    }

    public function test_accounts_feed_returns_json_page(): void
    {
        $user = $this->accountingUser();

        $this->actingAs($user)
            ->getJson(route('accounts.feed', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonMissingPath('next_page_url')
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
            ]);
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
