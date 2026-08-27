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
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

    public function test_report_finds_pasted_chassis_across_companies(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $ir = $this->locationStatus('trip_to_iran_bazargan');
        $first = $this->makeCompany('First Co');
        $second = $this->makeCompany('Second Co');
        $this->addCar($this->makeTrip($first, $user), 'LBECNAFD4TZ679845', $uz->id);
        $this->addCar($this->makeTrip($second, $user), 'WVWZZZ3CZWE123456', $ir->id);
        $this->addCar($this->makeTrip($second, $user), 'WVWZZZ3CZWE999999', $ir->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'chassis_text' => "LBECNAFD4TZ679845\nWVWZZZ3CZWE123456\nMISSINGVIN0000001",
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scoped', true)
                ->has('cars.data', 2)
                ->where('missingChassis', ['M1SS1NGV1N0000001'])
                ->where('cars.data.0.company_name', 'First Co')
                ->where('cars.data.1.company_name', 'Second Co'));
    }

    public function test_report_cleans_pasted_chassis_replaces_letter_o_and_flags_duplicates(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Paste Clean Co');
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE111111', $uz->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'chassis_text' => "WVWZZZ3CZWE111111; WVWOZZ3CZWE222222, WVWZZZ3CZWE111111;\nMISSINGVIN0000001",
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scoped', true)
                ->has('cars.data', 1)
                ->where('filters.chassis_text', "WVWZZZ3CZWE111111\nWVW0ZZ3CZWE222222\nM1SS1NGV1N0000001")
                ->has('filters.chassis_nos', 3)
                ->where('duplicateChassis', ['WVWZZZ3CZWE111111'])
                ->where('cars.data.0.is_duplicate', true)
                ->where('missingChassis', ['WVW0ZZ3CZWE222222', 'M1SS1NGV1N0000001']));
    }

    public function test_report_replaces_letter_i_with_one_when_matching_chassis(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Letter I Co');
        $this->addCar($this->makeTrip($company, $user), 'WVW1ZZ3CZWE444444', $uz->id);
        $this->addCar($this->makeTrip($company, $user), 'WVWIZZ3CZWE666666', $uz->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'chassis_text' => "WVWIZZ3CZWE444444; wvwizz3czwe555555\nWVW1ZZ3CZWE666666",
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cars.data', 2)
                ->where('filters.chassis_text', "WVW1ZZ3CZWE444444\nWVW1ZZ3CZWE555555\nWVW1ZZ3CZWE666666")
                ->where('missingChassis', ['WVW1ZZ3CZWE555555']));
    }

    public function test_report_splits_slash_separated_chassis_and_replaces_letter_o(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Slash Co');
        $this->addCar($this->makeTrip($company, $user), 'LFMAAA0C7T0726814', $uz->id);
        $this->addCar($this->makeTrip($company, $user), 'LFMAAA0C1T0726856', $uz->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'chassis_text' => 'LFMAAA0C7T0726814/LFMAAA0C1T0726713/LFMAAAOC1T0726856',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cars.data', 2)
                ->where('filters.chassis_text', "LFMAAA0C7T0726814\nLFMAAA0C1T0726713\nLFMAAA0C1T0726856")
                ->has('filters.chassis_nos', 3)
                ->where('missingChassis', ['LFMAAA0C1T0726713']));
    }

    public function test_report_keeps_duplicate_flag_after_paste_is_cleaned(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Hint Co');
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE151515', $uz->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'chassis_text' => 'WVWZZZ3CZWE151515',
                'duplicate_chassis' => ['WVWZZZ3CZWE151515'],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('duplicateChassis', ['WVWZZZ3CZWE151515'])
                ->where('cars.data.0.is_duplicate', true));
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

    public function test_excel_export_accepts_pasted_chassis_only(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Paste Co');
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE121212', $uz->id);
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE131313', $uz->id);

        $response = $this->actingAs($user)
            ->get(route('reports.land-trips.export.excel', [
                'chassis_text' => "WVWZZZ3CZWE121212\tWVWZZZ3CZWE000000",
            ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertSame(['WVWZZZ3CZWE121212'], $this->chassisFromExcel($content));
        $this->assertSame(['WVWZZZ3CZWE000000'], $this->missingFromExcel($content));
    }

    public function test_excel_export_puts_serial_first_and_keeps_missing_section_separate(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Serial Co');
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE141414', $uz->id);

        $response = $this->actingAs($user)
            ->get(route('reports.land-trips.export.excel', [
                'chassis_text' => 'WVWZZZ3CZWE141414; MISSINGVIN0000002',
            ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertSame(['WVWZZZ3CZWE141414'], $this->chassisFromExcel($content));
        $this->assertSame(['M1SS1NGV1N0000002'], $this->missingFromExcel($content));

        $path = tempnam(sys_get_temp_dir(), 'lrep');
        $this->assertNotFalse($path);
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('#', $sheet->getCell('A2')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('A3')->getValue());
        $this->assertSame('VIN', $sheet->getCell('G2')->getValue());
    }

    public function test_excel_inserts_yellow_blank_row_when_paste_contains_star(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $alpha = $this->makeCompany('Alpha Split');
        $zeta = $this->makeCompany('Zeta Split');
        $this->addCar($this->makeTrip($alpha, $user), 'WVWZZZ3CZWE161616', $uz->id);
        $this->addCar($this->makeTrip($zeta, $user), 'WVWZZZ3CZWE171717', $uz->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'chassis_text' => "WVWZZZ3CZWE171717\n*\nWVWZZZ3CZWE161616",
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.chassis_text', "WVWZZZ3CZWE171717\n*\nWVWZZZ3CZWE161616"));

        $response = $this->actingAs($user)
            ->get(route('reports.land-trips.export.excel', [
                'chassis_text' => "WVWZZZ3CZWE171717\n*\nWVWZZZ3CZWE161616",
            ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertSame(['WVWZZZ3CZWE171717', 'WVWZZZ3CZWE161616'], $this->chassisFromExcel($content));

        $path = tempnam(sys_get_temp_dir(), 'lrep');
        $this->assertNotFalse($path);
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('WVWZZZ3CZWE171717', trim((string) $sheet->getCell('G3')->getValue()));
        $this->assertSame('', trim((string) $sheet->getCell('G4')->getValue()));
        $this->assertSame('', trim((string) $sheet->getCell('A4')->getValue()));
        $this->assertSame('WVWZZZ3CZWE161616', trim((string) $sheet->getCell('G5')->getValue()));
        $this->assertSame(2, (int) $sheet->getCell('A5')->getValue());
        $this->assertSame(Fill::FILL_SOLID, $sheet->getStyle('A4')->getFill()->getFillType());
        $this->assertSame('FFE599', $sheet->getStyle('A4')->getFill()->getStartColor()->getRGB());
    }

    public function test_excel_export_follows_pasted_chassis_order(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $alpha = $this->makeCompany('Alpha Order');
        $zeta = $this->makeCompany('Zeta Order');
        $this->addCar($this->makeTrip($alpha, $user), 'WVWZZZ3CZWE202020', $uz->id);
        $this->addCar($this->makeTrip($zeta, $user), 'WVWZZZ3CZWE212121', $uz->id);

        $response = $this->actingAs($user)
            ->get(route('reports.land-trips.export.excel', [
                'chassis_text' => "WVWZZZ3CZWE212121\nWVWZZZ3CZWE202020",
            ]));

        $response->assertOk();
        $this->assertSame(
            ['WVWZZZ3CZWE212121', 'WVWZZZ3CZWE202020'],
            $this->chassisFromExcel($response->streamedContent())
        );
    }

    public function test_report_keeps_star_separator_when_sent_as_wire_token(): void
    {
        $user = $this->reporter();
        $uz = $this->locationStatus('loaded_in_bukhara');
        $company = $this->makeCompany('Sep Co');
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE181818', $uz->id);
        $this->addCar($this->makeTrip($company, $user), 'WVWZZZ3CZWE191919', $uz->id);

        $this->actingAs($user)
            ->get(route('reports.land-trips', [
                'chassis_text' => "WVWZZZ3CZWE181818\n__SEP__\nWVWZZZ3CZWE191919",
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.chassis_text', "WVWZZZ3CZWE181818\n*\nWVWZZZ3CZWE191919")
                ->has('cars.data', 2));
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
        for ($row = 3, $last = $sheet->getHighestRow(); $row <= $last; $row++) {
            $heading = trim((string) $sheet->getCell("A{$row}")->getValue());
            if (strcasecmp($heading, 'Not found chassis') === 0) {
                break;
            }

            $vin = trim((string) $sheet->getCell("G{$row}")->getValue());
            if ($vin !== '') {
                $values[] = $vin;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function missingFromExcel(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'lrep');
        $this->assertNotFalse($path);
        file_put_contents($path, $content);

        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $values = [];
        $inMissing = false;
        for ($row = 1, $last = $sheet->getHighestRow(); $row <= $last; $row++) {
            $heading = trim((string) $sheet->getCell("A{$row}")->getValue());
            if (strcasecmp($heading, 'Not found chassis') === 0) {
                $inMissing = true;

                continue;
            }
            if (! $inMissing) {
                continue;
            }

            $vin = trim((string) $sheet->getCell("B{$row}")->getValue());
            if ($vin !== '' && strcasecmp($vin, 'VIN') !== 0) {
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
