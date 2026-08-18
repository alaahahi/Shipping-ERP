<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Country;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandTripChassisDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_duplicate_check_groups_cars_sharing_last_six_digits_within_company(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany('Transit Co');
        $other = $this->makeCompany('Other Co');
        $trip = $this->makeTrip($company, $user, 'CMR-A');
        $otherTrip = $this->makeTrip($other, $user, 'CMR-B');

        $first = $this->addCar($trip, 'WVWZZZ3CZWE123456', 'Passat');
        $second = $this->addCar($trip, 'JTD123456', 'Camry');
        $this->addCar($trip, 'UNIQUE987654', 'Unique');
        $this->addCar($otherTrip, 'ABC123456', 'Elsewhere');

        $this->actingAs($user)
            ->getJson(route('land-trips.companies.cars.duplicates', $company))
            ->assertOk()
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.chassis_no', '123456')
            ->assertJsonPath('groups.0.match', 'last6')
            ->assertJsonPath('groups.0.count', 2)
            ->assertJsonPath('groups.0.cars.0.id', $first->id)
            ->assertJsonPath('groups.0.cars.1.id', $second->id);
    }

    public function test_duplicate_check_ignores_unique_last_six_digits(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany('Solo Co');
        $trip = $this->makeTrip($company, $user, 'CMR-C');
        $this->addCar($trip, 'WVWZZZ3CZWE123456', 'Only');

        $this->actingAs($user)
            ->getJson(route('land-trips.companies.cars.duplicates', $company))
            ->assertOk()
            ->assertJsonCount(0, 'groups');
    }

    private function landTripsUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::LandTripsView->value);

        return $user;
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

    private function addCar(LandTrip $trip, string $chassis, string $model): LandTripCar
    {
        return LandTripCar::query()->create([
            'land_trip_id' => $trip->id,
            'chassis_no' => $chassis,
            'consignee_name' => $model,
            'model' => $model,
            'sort_order' => 1,
        ]);
    }
}
