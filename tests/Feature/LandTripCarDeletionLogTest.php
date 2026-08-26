<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\LandTripCarDeletionSource;
use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Country;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarDeletion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandTripCarDeletionLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manager_delete_writes_a_deletion_log(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Delete Co');
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-DEL'), 'WVWZZZ3CZWE123456');

        $this->actingAs($user)
            ->delete(route('land-trips.companies.cars.destroy', $company), [
                'car_ids' => [$car->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($car);

        $deletion = LandTripCarDeletion::query()->firstOrFail();
        $this->assertSame($company->id, $deletion->company_id);
        $this->assertSame($user->id, $deletion->user_id);
        $this->assertSame(1, $deletion->cars_count);
        $this->assertSame(LandTripCarDeletionSource::Manual, $deletion->source);
        $this->assertSame($car->id, $deletion->items()->value('land_trip_car_id'));
        $this->assertSame('WVWZZZ3CZWE123456', $deletion->items()->value('chassis_no'));
    }

    public function test_manager_can_view_deletion_log_and_restore_a_car(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Restore Co');
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-RST'), 'JTDKB20U377123456');

        $this->actingAs($user)
            ->delete(route('land-trips.companies.cars.destroy', $company), [
                'car_ids' => [$car->id],
            ])
            ->assertRedirect();

        $deletion = LandTripCarDeletion::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('land-trips.companies.deletion-logs', $company))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LandTrips/DeletionLogs')
                ->where('canManage', true)
                ->has('deletions.data', 1)
                ->where('deletionLog.unrestored_count', 1));

        $this->actingAs($user)
            ->post(route('land-trips.companies.deletion-logs.restore', $company), [
                'deletion_id' => $deletion->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('land_trip_cars', [
            'id' => $car->id,
            'deleted_at' => null,
        ]);

        $deletion->refresh();
        $this->assertNotNull($deletion->restored_at);
        $this->assertSame($user->id, $deletion->restored_by);
        $this->assertNotNull($deletion->items()->value('restored_at'));
    }

    public function test_manager_can_restore_one_car_from_a_batch(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Batch Co');
        $trip = $this->makeTrip($company, $user, 'CMR-BAT');
        $first = $this->addCar($trip, 'WVWZZZ3CZWE111111');
        $second = $this->addCar($trip, 'WVWZZZ3CZWE222222');

        $this->actingAs($user)
            ->delete(route('land-trips.companies.cars.destroy', $company), [
                'car_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $deletion = LandTripCarDeletion::query()->firstOrFail();
        $itemId = $deletion->items()->where('land_trip_car_id', $first->id)->value('id');

        $this->actingAs($user)
            ->post(route('land-trips.companies.deletion-logs.restore', $company), [
                'deletion_id' => $deletion->id,
                'item_ids' => [$itemId],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('land_trip_cars', [
            'id' => $first->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted($second);

        $deletion->refresh();
        $this->assertNull($deletion->restored_at);
        $this->assertSame(1, $deletion->items()->whereNull('restored_at')->count());
    }

    public function test_viewer_cannot_restore_cars(): void
    {
        $manager = $this->manager();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::LandTripsView->value);
        $company = $this->makeCompany('View Co');
        $car = $this->addCar($this->makeTrip($company, $manager, 'CMR-VW'), 'WVWZZZ3CZWE333333');

        $this->actingAs($manager)
            ->delete(route('land-trips.companies.cars.destroy', $company), [
                'car_ids' => [$car->id],
            ])
            ->assertRedirect();

        $deletion = LandTripCarDeletion::query()->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('land-trips.companies.deletion-logs', $company))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('land-trips.companies.deletion-logs.restore', $company), [
                'deletion_id' => $deletion->id,
            ])
            ->assertForbidden();

        $this->assertSoftDeleted($car);
    }

    public function test_cannot_restore_another_company_deletion(): void
    {
        $user = $this->manager();
        $from = $this->makeCompany('From Co');
        $other = $this->makeCompany('Other Co');
        $car = $this->addCar($this->makeTrip($from, $user, 'CMR-FR'), 'WVWZZZ3CZWE444444');

        $this->actingAs($user)
            ->delete(route('land-trips.companies.cars.destroy', $from), [
                'car_ids' => [$car->id],
            ])
            ->assertRedirect();

        $deletion = LandTripCarDeletion::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('land-trips.companies.deletion-logs.restore', $other), [
                'deletion_id' => $deletion->id,
            ])
            ->assertSessionHasErrors('deletion_id');

        $this->assertSoftDeleted($car);
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
