<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Country;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarCompanyTransfer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandTripCarTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manager_can_transfer_cars_and_writes_a_log(): void
    {
        $user = $this->manager();
        $from = $this->makeCompany('From Co');
        $to = $this->makeCompany('To Co');
        $trip = $this->makeTrip($from, $user, 'CMR-FROM');
        $car = $this->addCar($trip, 'WVWZZZ3CZWE123456');

        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.transfer', $from), [
                'to_company_id' => $to->id,
                'car_ids' => [$car->id],
                'notes' => 'Ops move',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $car->refresh();
        $this->assertSame($to->id, $car->landTrip()->value('company_id'));

        $transfer = LandTripCarCompanyTransfer::query()->firstOrFail();
        $this->assertSame($from->id, $transfer->from_company_id);
        $this->assertSame($to->id, $transfer->to_company_id);
        $this->assertSame(1, $transfer->cars_count);
        $this->assertSame('Ops move', $transfer->notes);
        $this->assertSame($car->id, $transfer->items()->value('land_trip_car_id'));
        $this->assertSame('WVWZZZ3CZWE123456', $transfer->items()->value('chassis_no'));
    }

    public function test_cannot_transfer_cars_that_belong_to_another_company(): void
    {
        $user = $this->manager();
        $from = $this->makeCompany('A Co');
        $other = $this->makeCompany('B Co');
        $target = $this->makeCompany('C Co');
        $foreignCar = $this->addCar($this->makeTrip($other, $user, 'CMR-B'), 'JTDKB20U377123456');

        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.transfer', $from), [
                'to_company_id' => $target->id,
                'car_ids' => [$foreignCar->id],
            ])
            ->assertSessionHasErrors('car_ids');

        $this->assertSame($other->id, $foreignCar->fresh()->landTrip()->value('company_id'));
        $this->assertSame(0, LandTripCarCompanyTransfer::query()->count());
    }

    public function test_cannot_transfer_to_the_same_company(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Same Co');
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-S'), 'WVWZZZ3CZWE654321');

        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.transfer', $company), [
                'to_company_id' => $company->id,
                'car_ids' => [$car->id],
            ])
            ->assertSessionHasErrors('to_company_id');
    }

    public function test_viewer_cannot_transfer_cars(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::LandTripsView->value);
        $company = $this->makeCompany('View Co');
        $target = $this->makeCompany('Other');
        $manager = $this->manager();
        $car = $this->addCar($this->makeTrip($company, $manager, 'CMR-V'), 'WVWZZZ3CZWE111111');

        $this->actingAs($viewer)
            ->post(route('land-trips.companies.cars.transfer', $company), [
                'to_company_id' => $target->id,
                'car_ids' => [$car->id],
            ])
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

    private function addCar(LandTrip $trip, string $chassis): LandTripCar
    {
        return LandTripCar::query()->create([
            'land_trip_id' => $trip->id,
            'chassis_no' => $chassis,
            'consignee_name' => 'Test',
            'model' => 'Test',
            'sort_order' => 1,
        ]);
    }
}
