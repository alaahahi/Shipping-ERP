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
        $this->assertSame($user->id, $transfer->user_id);
        $this->assertSame(1, $transfer->cars_count);
        $this->assertSame('Ops move', $transfer->notes);
        $this->assertNotNull($transfer->created_at);
        $this->assertSame($car->id, $transfer->items()->value('land_trip_car_id'));
        $this->assertSame('WVWZZZ3CZWE123456', $transfer->items()->value('chassis_no'));
    }

    public function test_transfer_restamps_entry_date_and_stores_actor_and_transfer_time(): void
    {
        $this->travelTo('2026-11-14 12:00:00');

        $user = $this->manager();
        $from = $this->makeCompany('Source Co');
        $to = $this->makeCompany('Dest Co');
        $trip = $this->makeTrip($from, $user, 'CMR-DATE');
        $car = $this->addCar($trip, 'WVWZZZ3CZWE777777');

        // Simulate car entered about a month earlier on the source account.
        $oldEntry = now()->copy()->subMonth()->setTime(9, 15, 0);
        LandTripCar::query()->whereKey($car->id)->update([
            'created_at' => $oldEntry,
            'updated_at' => $oldEntry,
        ]);
        $car->refresh();
        $this->assertSame($oldEntry->toDateTimeString(), $car->created_at->toDateTimeString());

        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.transfer', $from), [
                'to_company_id' => $to->id,
                'car_ids' => [$car->id],
                'notes' => 'Date check',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $car->refresh();
        $transfer = LandTripCarCompanyTransfer::query()->latest('id')->firstOrFail();

        $this->assertSame($to->id, $car->landTrip()->value('company_id'));
        $this->assertSame(now()->toDateTimeString(), $car->created_at->toDateTimeString());
        $this->assertTrue($car->created_at->greaterThan($oldEntry));
        $this->assertSame($user->id, $transfer->user_id);
        $this->assertSame(now()->toDateTimeString(), $transfer->created_at->toDateTimeString());
        $this->assertSame('Date check', $transfer->notes);

        $this->actingAs($user)
            ->get(route('land-trips.companies.transfer-logs', $to))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LandTrips/TransferLogs')
                ->where('transfers.data.0.user_id', $user->id)
                ->where('transfers.data.0.user_name', $user->name)
                ->where('transfers.data.0.from_company_id', $from->id)
                ->where('transfers.data.0.to_company_id', $to->id)
                ->where('transfers.data.0.cars_count', 1)
            );
    }

    public function test_cannot_transfer_to_inactive_company(): void
    {
        $user = $this->manager();
        $from = $this->makeCompany('Active From');
        $to = $this->makeCompany('Inactive To');
        $to->update(['is_active' => false]);
        $car = $this->addCar($this->makeTrip($from, $user, 'CMR-INACT'), 'WVWZZZ3CZWE888888');

        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.transfer', $from), [
                'to_company_id' => $to->id,
                'car_ids' => [$car->id],
            ])
            ->assertSessionHasErrors('to_company_id');

        $this->assertSame($from->id, $car->fresh()->landTrip()->value('company_id'));
        $this->assertSame(0, LandTripCarCompanyTransfer::query()->count());
    }

    public function test_cannot_transfer_cars_from_posted_or_closed_trip(): void
    {
        $user = $this->manager();
        $from = $this->makeCompany('Posted From');
        $to = $this->makeCompany('Posted To');
        $trip = $this->makeTrip($from, $user, 'CMR-POSTED');
        $trip->update(['status' => LandTripStatus::Closed]);
        $car = $this->addCar($trip, 'WVWZZZ3CZWE999999');

        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.transfer', $from), [
                'to_company_id' => $to->id,
                'car_ids' => [$car->id],
            ])
            ->assertSessionHasErrors('car_ids');

        $this->assertSame($from->id, $car->fresh()->landTrip()->value('company_id'));
        $this->assertSame(0, LandTripCarCompanyTransfer::query()->count());
    }

    public function test_transfer_updates_consignee_when_it_was_the_old_trader(): void
    {
        $user = $this->manager();
        $from = $this->makeCompany('Old Trader');
        $to = $this->makeCompany('New Trader');
        $trip = $this->makeTrip($from, $user, 'CMR-TR');
        $car = $this->addCar($trip, 'WVWZZZ3CZWE555555');
        $car->update(['consignee_name' => 'Old Trader']);
        $kept = $this->addCar($trip, 'WVWZZZ3CZWE666666');
        $kept->update(['consignee_name' => 'Custom Consignee']);

        $this->actingAs($user)
            ->post(route('land-trips.companies.cars.transfer', $from), [
                'to_company_id' => $to->id,
                'car_ids' => [$car->id, $kept->id],
            ])
            ->assertRedirect();

        $this->assertSame('New Trader', $car->fresh()->consignee_name);
        $this->assertSame('Custom Consignee', $kept->fresh()->consignee_name);
        $this->assertSame($to->id, $car->fresh()->landTrip()->value('company_id'));

        $this->actingAs($user)
            ->get(route('land-trips.companies.car-check', $to))
            ->assertOk()
            ->assertJsonFragment([
                'chassis_no' => 'WVWZZZ3CZWE555555',
                'consignee_name' => 'New Trader',
            ])
            ->assertJsonFragment([
                'chassis_no' => 'WVWZZZ3CZWE666666',
                'consignee_name' => 'Custom Consignee',
            ]);

        $this->actingAs($user)
            ->get(route('land-trips.companies.car-check', $from))
            ->assertOk()
            ->assertJsonPath('own', []);
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
