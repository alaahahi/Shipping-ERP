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
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LandTripCarReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_report_page_shows_cars_for_selected_country(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $ir = $this->locationStatus('trip_to_iran_bazargan');
        $company = $this->makeCompany('Report Co');
        $trip = $this->makeTrip($company, $user);
        $this->addCar($trip, 'WVWZZZ3CZWE111111', $uz->id);
        $this->addCar($trip, 'WVWZZZ3CZWE222222', $ir->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'country_ids' => [$uz->country_id],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/LandTrips')
                ->where('scoped', true)
                ->has('cars.data', 1)
                ->where('cars.data.0.chassis_no', 'WVWZZZ3CZWE111111'));
    }

    public function test_report_can_include_multiple_locations(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $ir = $this->locationStatus('trip_to_iran_bazargan');
        $company = $this->makeCompany('Multi Loc Co');
        $trip = $this->makeTrip($company, $user);
        $this->addCar($trip, 'WVWZZZ3CZWE333333', $uz->id);
        $this->addCar($trip, 'WVWZZZ3CZWE444444', $ir->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'location_status_ids' => [$uz->id, $ir->id],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cars.data', 2));
    }

    public function test_report_orders_cars_by_company_name(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $zeta = $this->makeCompany('Zeta Transit');
        $alpha = $this->makeCompany('Alpha Transit');
        $this->addCar($this->makeTrip($zeta, $user), 'WVWZZZ3CZWE777777', $uz->id);
        $this->addCar($this->makeTrip($alpha, $user), 'WVWZZZ3CZWE888888', $uz->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'location_status_ids' => [$uz->id],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cars.data.0.company_name', 'Alpha Transit')
                ->where('cars.data.0.chassis_no', 'WVWZZZ3CZWE888888')
                ->where('cars.data.1.company_name', 'Zeta Transit')
                ->where('cars.data.1.chassis_no', 'WVWZZZ3CZWE777777'));
    }

    public function test_excel_export_filters_by_location_and_keeps_vin_as_text(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $ir = $this->locationStatus('trip_to_iran_bazargan');
        $company = $this->makeCompany('Excel Co');
        $trip = $this->makeTrip($company, $user);
        $this->addCar($trip, 'LBECNAFD4TZ679845', $uz->id);
        $this->addCar($trip, 'WVWZZZ3CZWE555555', $ir->id);

        $response = $this->actingAs($user)
            ->get(route('reports.land-trips.export.excel', [
                'location_status_ids' => [$uz->id],
            ]));

        $response->assertOk();
        $this->assertSame(['LBECNAFD4TZ679845'], $this->chassisFromExcel($response->streamedContent()));
    }

    public function test_pdf_export_requires_a_country_or_location(): void
    {
        $user = $this->reporter();

        $this->actingAs($user)
            ->from(route('reports.land-trips'))
            ->get(route('reports.land-trips.export.pdf'))
            ->assertRedirect(route('reports.land-trips'))
            ->assertSessionHasErrors('country_ids');
    }

    public function test_pdf_export_downloads_for_selected_country(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Pdf Co');
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE666666', $uz->id);

        $response = $this->actingAs($user)
            ->get(route('reports.land-trips.export.pdf', [
                'country_ids' => [$uz->country_id],
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_user_without_reports_permission_cannot_open_land_trip_report(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::LandTripsView->value);

        $this->actingAs($user)
            ->get(route('reports.land-trips'))
            ->assertForbidden();
    }

    /**
     * @return list<string>
     */
    private function chassisFromExcel(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'lrep');
        $this->assertNotFalse($path);
        file_put_contents($path, $content);

        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $values = [];
        for ($row = 2, $last = $sheet->getHighestRow(); $row <= $last; $row++) {
            $vin = trim((string) $sheet->getCell("H{$row}")->getValue());
            if ($vin !== '') {
                $values[] = $vin;
            }
        }

        return $values;
    }

    private function reporter(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::ReportsView->value,
            Permission::LandTripsView->value,
        ]);

        return $user;
    }

    private function locationStatus(string $code): LandTripCarStatus
    {
        $status = LandTripCarStatus::query()->where('code', $code)->firstOrFail();
        $this->assertNotNull($status->country_id);

        return $status;
    }

    private function makeCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function makeTrip(Company $company, User $user): LandTrip
    {
        $from = Country::query()->where('iso_code', 'IQ')->firstOrFail();
        $to = Country::query()->where('iso_code', 'TR')->firstOrFail();

        return LandTrip::query()->create([
            'cmr_number' => 'CMR-R-'.uniqid(),
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
            'model' => 'Test',
            'price' => 100,
            'sort_order' => 1,
        ]);
    }
}
