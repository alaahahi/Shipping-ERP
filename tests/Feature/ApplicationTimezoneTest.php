<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\SettingKey;
use App\Models\Ship;
use App\Models\User;
use App\Services\SettingService;
use App\Support\ApplicationTimezone;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        ApplicationTimezone::apply(ApplicationTimezone::DEFAULT);

        parent::tearDown();
    }

    public function test_application_timezone_defaults_to_asia_baghdad(): void
    {
        $this->assertSame('Asia/Baghdad', config('app.timezone'));
        $this->assertSame('Asia/Baghdad', date_default_timezone_get());
    }

    public function test_now_is_three_hours_ahead_of_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'UTC'));

        $this->assertSame('2026-08-08 15:00', ApplicationTimezone::formatNow());
        $this->assertSame('2026-08-08 15:00 Asia/Baghdad', ApplicationTimezone::formatNowLabel());
        $this->assertSame('+03:00', now()->timezone(ApplicationTimezone::DEFAULT)->format('P'));
    }

    public function test_null_datetime_formats_as_empty_string(): void
    {
        $this->assertSame('', ApplicationTimezone::formatDateTime(null));
    }

    public function test_expense_print_date_uses_local_timezone(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::ShipsView->value);

        $ship = Ship::query()->create([
            'name' => 'Test Ship',
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'UTC'));

        $this->actingAs($user)
            ->get(route('ships.expenses.print', $ship))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ships/ExpensePrint')
                ->where('printedAt', '2026-08-08 15:00 Asia/Baghdad')
            );
    }

    public function test_settings_timezone_is_applied_on_web_requests(): void
    {
        $this->seed(SettingsSeeder::class);

        app(SettingService::class)->updateMany([
            SettingKey::AppTimezone->value => 'Asia/Dubai',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'UTC'));

        $this->get('/login')->assertOk();

        $this->assertSame('Asia/Dubai', config('app.timezone'));
        $this->assertSame('2026-08-08 16:00', ApplicationTimezone::formatNow());
    }
}
