<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\Country;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\LandTripCarPriceChange;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandTripCarBulkPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manager_can_bulk_update_company_car_prices_and_write_a_log(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Price Co');
        $trip = $this->makeTrip($company, $user, 'CMR-P1');
        $first = $this->addCar($trip, 'WVWZZZ3CZWE123450', 500);
        $second = $this->addCar($trip, 'WVWZZZ3CZWE123451', 650);

        $this->actingAs($user)
            ->patch(route('land-trips.companies.cars.bulk-price', $company), [
                'car_ids' => [$first->id, $second->id],
                'price' => 775,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('775.00', $first->fresh()->price);
        $this->assertSame('775.00', $second->fresh()->price);

        $change = LandTripCarPriceChange::query()->with('items')->firstOrFail();
        $this->assertSame($company->id, $change->company_id);
        $this->assertSame($user->id, $change->user_id);
        $this->assertSame(2, $change->cars_count);
        $this->assertSame('775.00', $change->new_price);
        $this->assertCount(2, $change->items);
        $this->assertEqualsCanonicalizing(
            [
                [$first->id, 'WVWZZZ3CZWE123450', '500.00', '775.00'],
                [$second->id, 'WVWZZZ3CZWE123451', '650.00', '775.00'],
            ],
            $change->items->map(fn ($item) => [
                $item->land_trip_car_id,
                $item->chassis_no,
                $item->old_price,
                $item->new_price,
            ])->all()
        );
    }

    public function test_bulk_price_edit_rejects_cars_from_another_company(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Main Co');
        $other = $this->makeCompany('Other Co');
        $foreignCar = $this->addCar($this->makeTrip($other, $user, 'CMR-P2'), 'JTDKB20U377123450', 900);

        $this->actingAs($user)
            ->patch(route('land-trips.companies.cars.bulk-price', $company), [
                'car_ids' => [$foreignCar->id],
                'price' => 1000,
            ])
            ->assertSessionHasErrors('car_ids');

        $this->assertSame('900.00', $foreignCar->fresh()->price);
        $this->assertSame(0, LandTripCarPriceChange::query()->count());
    }

    public function test_viewer_cannot_bulk_edit_prices(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::LandTripsView->value);
        $manager = $this->manager();
        $company = $this->makeCompany('View Only Co');
        $car = $this->addCar($this->makeTrip($company, $manager, 'CMR-P3'), 'WVWZZZ3CZWE999999', 300);

        $this->actingAs($viewer)
            ->patch(route('land-trips.companies.cars.bulk-price', $company), [
                'car_ids' => [$car->id],
                'price' => 301,
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

    private function addCar(LandTrip $trip, string $chassis, float $price): LandTripCar
    {
        return LandTripCar::query()->create([
            'land_trip_id' => $trip->id,
            'chassis_no' => $chassis,
            'consignee_name' => 'Test',
            'model' => 'Test',
            'price' => $price,
            'sort_order' => 1,
        ]);
    }
}
