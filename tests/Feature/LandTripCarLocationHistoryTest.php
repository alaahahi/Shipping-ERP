<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Country;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LandTripCarLocationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_history_builds_stays_with_durations_after_location_moves(): void
    {
        $user = $this->manager();
        $from = $this->locationStatus('loaded_in_bukhara');
        $mid = $this->locationStatus('trip_to_iran_bazargan');
        $to = $this->locationStatus('from_iran_to_erbil');
        $company = $this->makeCompany('History Co');

        Carbon::setTestNow('2026-08-01 08:00:00');
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-H'), 'WVWZZZ3CZWE123456', $from->id);

        Carbon::setTestNow('2026-08-04 10:30:00');
        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.location', $company), [
                'location_status_id' => $mid->id,
                'scope' => 'selected',
                'car_ids' => [$car->id],
            ])
            ->assertRedirect();

        Carbon::setTestNow('2026-08-06 12:00:00');
        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.location', $company), [
                'location_status_id' => $to->id,
                'scope' => 'selected',
                'car_ids' => [$car->id],
            ])
            ->assertRedirect();

        Carbon::setTestNow('2026-08-07 15:20:00');

        $this->actingAs($user)
            ->getJson(route('land-trips.companies.cars.location-history', [$company, $car]))
            ->assertOk()
            ->assertJsonPath('car.id', $car->id)
            ->assertJsonPath('car.chassis_no', 'WVWZZZ3CZWE123456')
            ->assertJsonCount(3, 'stays')
            ->assertJsonPath('stays.0.location_label', $from->localizedName('ar'))
            ->assertJsonPath('stays.0.arrived_at', '2026-08-01 08:00')
            ->assertJsonPath('stays.0.left_at', '2026-08-04 10:30')
            ->assertJsonPath('stays.0.is_current', false)
            ->assertJsonPath('stays.0.duration.days', 3)
            ->assertJsonPath('stays.0.duration.hours', 2)
            ->assertJsonPath('stays.0.duration.minutes', 30)
            ->assertJsonPath('stays.1.location_label', $mid->localizedName('ar'))
            ->assertJsonPath('stays.1.arrived_at', '2026-08-04 10:30')
            ->assertJsonPath('stays.1.left_at', '2026-08-06 12:00')
            ->assertJsonPath('stays.1.changed_by', $user->name)
            ->assertJsonPath('stays.1.duration.days', 2)
            ->assertJsonPath('stays.1.duration.hours', 1)
            ->assertJsonPath('stays.1.duration.minutes', 30)
            ->assertJsonPath('stays.2.location_label', $to->localizedName('ar'))
            ->assertJsonPath('stays.2.arrived_at', '2026-08-06 12:00')
            ->assertJsonPath('stays.2.left_at', null)
            ->assertJsonPath('stays.2.is_current', true)
            ->assertJsonPath('stays.2.duration.days', 1)
            ->assertJsonPath('stays.2.duration.hours', 3)
            ->assertJsonPath('stays.2.duration.minutes', 20);
    }

    public function test_history_without_moves_returns_the_current_stay(): void
    {
        $user = $this->manager();
        $status = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Idle Co');

        Carbon::setTestNow('2026-08-01 09:00:00');
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-IDLE'), 'JTDKB20U377123456', $status->id);

        Carbon::setTestNow('2026-08-03 09:00:00');

        $this->actingAs($user)
            ->getJson(route('land-trips.companies.cars.location-history', [$company, $car]))
            ->assertOk()
            ->assertJsonCount(1, 'stays')
            ->assertJsonPath('stays.0.location_label', $status->localizedName('ar'))
            ->assertJsonPath('stays.0.arrived_at', '2026-08-01 09:00')
            ->assertJsonPath('stays.0.left_at', null)
            ->assertJsonPath('stays.0.is_current', true)
            ->assertJsonPath('stays.0.duration.days', 2);
    }

    public function test_history_returns_not_found_for_a_car_from_another_company(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('A Co');
        $other = $this->makeCompany('B Co');
        $status = $this->locationStatus('loaded_in_bukhara');
        $foreignCar = $this->addCar($this->makeTrip($other, $user, 'CMR-B'), 'WVWZZZ3CZWE654321', $status->id);

        $this->actingAs($user)
            ->getJson(route('land-trips.companies.cars.location-history', [$company, $foreignCar]))
            ->assertNotFound();
    }

    public function test_viewer_can_read_location_history(): void
    {
        $manager = $this->manager();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::LandTripsView->value);
        $status = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('View Co');
        $car = $this->addCar($this->makeTrip($company, $manager, 'CMR-V'), 'WVWZZZ3CZWE111111', $status->id);

        $this->actingAs($viewer)
            ->getJson(route('land-trips.companies.cars.location-history', [$company, $car]))
            ->assertOk()
            ->assertJsonPath('car.id', $car->id);
    }

    public function test_outsider_cannot_read_location_history(): void
    {
        $manager = $this->manager();
        $outsider = User::factory()->create();
        $status = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Secret Co');
        $car = $this->addCar($this->makeTrip($company, $manager, 'CMR-S'), 'WVWZZZ3CZWE222222', $status->id);

        $this->actingAs($outsider)
            ->getJson(route('land-trips.companies.cars.location-history', [$company, $car]))
            ->assertForbidden();
    }

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::LandTripsView->value,
            Permission::LandTripsManage->value,
        ]);

        return $user;
    }

    private function locationStatus(string $code): LandTripCarStatus
    {
        return LandTripCarStatus::query()->where('code', $code)->firstOrFail();
    }

    private function makeCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function makeTrip(Company $company, User $user, string $cmr): LandTrip
    {
        $from = Country::query()->where('iso_code', 'IQ')->firstOrFail();
        $to = Country::query()->where('iso_code', 'TR')->firstOrFail();

        return LandTrip::query()->create([
            'cmr_number' => $cmr,
            'driver_name' => 'Driver',
            'from_country_id' => $from->id,
            'to_country_id' => $to->id,
            'departure_date' => '2026-08-01',
            'company_id' => $company->id,
            'freight_amount' => 0,
            'currency' => Currency::USD,
            'status' => LandTripStatus::Draft,
            'created_by' => $user->id,
        ]);
    }

    private function addCar(LandTrip $trip, string $chassis, int $locationStatusId): LandTripCar
    {
        return LandTripCar::query()->create([
            'land_trip_id' => $trip->id,
            'location_status_id' => $locationStatusId,
            'chassis_no' => $chassis,
            'consignee_name' => 'Test',
            'model' => 'Passat',
            'sort_order' => 1,
        ]);
    }
}
