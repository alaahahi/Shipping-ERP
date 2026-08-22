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
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LandTripCarExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_export_uses_search_filter(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Export Co');
        $trip = $this->makeTrip($company, $user, 'CMR-E1');
        $this->addCar($trip, 'WVWZZZ3CZWE111111');
        $this->addCar($trip, 'WVWZZZ3CZWE222222');
        $this->addCar($trip, 'JTDKB20U377333333');

        $response = $this->actingAs($user)
            ->get(route('land-trips.companies.export', $company).'?'.http_build_query([
                'search' => 'WVWZZZ3CZWE111111',
            ]));

        $response->assertOk();
        $this->assertSame(['WVWZZZ3CZWE111111'], $this->chassisFromExcel($response->streamedContent()));
    }

    public function test_export_uses_selected_car_ids_and_ignores_search(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Select Co');
        $trip = $this->makeTrip($company, $user, 'CMR-E2');
        $first = $this->addCar($trip, 'WVWZZZ3CZWE444444');
        $second = $this->addCar($trip, 'WVWZZZ3CZWE555555');
        $this->addCar($trip, 'WVWZZZ3CZWE666666');

        $response = $this->actingAs($user)
            ->get(route('land-trips.companies.export', $company).'?'.http_build_query([
                'search' => 'WVWZZZ3CZWE666666',
                'car_ids' => [$first->id, $second->id],
            ]));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            ['WVWZZZ3CZWE444444', 'WVWZZZ3CZWE555555'],
            $this->chassisFromExcel($response->streamedContent())
        );
    }

    public function test_export_does_not_include_cars_from_another_company(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Main Export Co');
        $other = $this->makeCompany('Other Export Co');
        $own = $this->addCar($this->makeTrip($company, $user, 'CMR-E3'), 'WVWZZZ3CZWE777777');
        $foreign = $this->addCar($this->makeTrip($other, $user, 'CMR-E4'), 'JTDKB20U377888888');

        $response = $this->actingAs($user)
            ->get(route('land-trips.companies.export', $company).'?'.http_build_query([
                'car_ids' => [$own->id, $foreign->id],
            ]));

        $response->assertOk();
        $this->assertSame(['WVWZZZ3CZWE777777'], $this->chassisFromExcel($response->streamedContent()));
    }

    public function test_print_uses_selected_car_ids(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Print Co');
        $trip = $this->makeTrip($company, $user, 'CMR-P1');
        $first = $this->addCar($trip, 'WVWZZZ3CZWE121212');
        $second = $this->addCar($trip, 'WVWZZZ3CZWE131313');
        $this->addCar($trip, 'WVWZZZ3CZWE141414');

        $this->actingAs($user)
            ->get(route('land-trips.companies.print', $company).'?'.http_build_query([
                'sort' => 'oldest',
                'car_ids' => [$first->id, $second->id],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LandTrips/CompanyCarsPrint')
                ->where('company.id', $company->id)
                ->where('summary.count', 2)
                ->where('filters.selected', true)
                ->has('cars', 2)
                ->where('cars.0.chassis_no', $first->chassis_no)
                ->where('cars.1.chassis_no', $second->chassis_no)
            );
    }

    public function test_viewer_can_print_filtered_cars(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::LandTripsView->value);
        $manager = $this->manager();
        $company = $this->makeCompany('View Print Co');
        $this->addCar($this->makeTrip($company, $manager, 'CMR-P2'), 'WVWZZZ3CZWE151515');
        $this->addCar($this->makeTrip($company, $manager, 'CMR-P3'), 'UNIQUECHASSIS99999');

        $this->actingAs($viewer)
            ->get(route('land-trips.companies.print', $company).'?'.http_build_query([
                'search' => 'UNIQUECHASSIS99999',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LandTrips/CompanyCarsPrint')
                ->where('summary.count', 1)
                ->where('filters.selected', false)
                ->where('cars.0.chassis_no', 'UNIQUECHASSIS99999')
            );
    }

    public function test_user_without_permission_cannot_export_or_print(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();
        $company = $this->makeCompany('Denied Co');
        $this->addCar($this->makeTrip($company, $manager, 'CMR-X'), 'WVWZZZ3CZWE161616');

        $this->actingAs($user)
            ->get(route('land-trips.companies.export', $company))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('land-trips.companies.print', $company))
            ->assertForbidden();
    }

    /**
     * @return list<string>
     */
    private function chassisFromExcel(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'cars');
        $this->assertNotFalse($path);
        file_put_contents($path, $content);

        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $values = [];
        for ($row = 3, $last = $sheet->getHighestRow(); $row <= $last; $row++) {
            $vin = trim((string) $sheet->getCell("E{$row}")->getValue());
            if ($vin !== '') {
                $values[] = $vin;
            }
        }

        return $values;
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
            'price' => 100,
            'sort_order' => 1,
        ]);
    }
}
