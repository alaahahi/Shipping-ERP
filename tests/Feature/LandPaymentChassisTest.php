<?php

namespace Tests\Feature;

use App\Enums\CompanyWalletEntryType;
use App\Enums\Currency;
use App\Enums\LandDriverPaymentType;
use App\Enums\LandTripStatus;
use App\Enums\Permission;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyWalletEntry;
use App\Models\Country;
use App\Models\LandDriverPayment;
use App\Models\LandPaymentChassis;
use App\Models\LandTrip;
use App\Models\LandTripCar;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandPaymentChassisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_manager_can_assign_company_chassis_to_a_wallet_payment(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Pay Co');
        $entry = $this->makeWalletEntry($company, $user);
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-P'), 'WVWZZZ3CZWE123456');

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.chassis', [$company, $entry]), [
                'car_ids' => [$car->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('land_payment_chassis', [
            'company_id' => $company->id,
            'payable_id' => $entry->id,
            'land_trip_car_id' => $car->id,
            'chassis_no' => 'WVWZZZ3CZWE123456',
        ]);
    }

    public function test_cannot_assign_another_companys_car_ids(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Home');
        $other = $this->makeCompany('Away');
        $entry = $this->makeWalletEntry($company, $user);
        $foreign = $this->addCar($this->makeTrip($other, $user, 'CMR-X'), 'JTDKB20U377123456');

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.chassis', [$company, $entry]), [
                'car_ids' => [$foreign->id],
            ])
            ->assertSessionHasErrors('car_ids');

        $this->assertSame(0, LandPaymentChassis::query()->count());
    }

    public function test_paste_keeps_valid_chassis_and_skips_unknown_lines(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Paste Co');
        $entry = $this->makeWalletEntry($company, $user);
        $this->addCar($this->makeTrip($company, $user, 'CMR-Y'), 'WVWZZZ3CZWE123456');

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.chassis', [$company, $entry]), [
                'chassis_text' => "WVWZZZ3CZWE123456\nUNKNOWNVIN0000001\n",
            ])
            ->assertRedirect();

        $this->assertSame(1, LandPaymentChassis::query()->count());
        $this->assertSame('WVWZZZ3CZWE123456', LandPaymentChassis::query()->value('chassis_no'));
    }

    public function test_wallet_print_includes_assigned_chassis(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Print Co');
        $entry = $this->makeWalletEntry($company, $user);
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-Z'), 'WVWZZZ3CZWE123456');

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.chassis', [$company, $entry]), [
                'car_ids' => [$car->id],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('land-trips.companies.wallet.print', [$company, $entry]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LandTrips/WalletPrint')
                ->where('entry.chassis.0.chassis_no', 'WVWZZZ3CZWE123456')
            );
    }

    public function test_viewer_cannot_assign_chassis(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::LandTripsView->value);
        $manager = $this->manager();
        $company = $this->makeCompany('View Pay');
        $entry = $this->makeWalletEntry($company, $manager);
        $car = $this->addCar($this->makeTrip($company, $manager, 'CMR-W'), 'WVWZZZ3CZWE123456');

        $this->actingAs($viewer)
            ->post(route('land-trips.companies.wallet.chassis', [$company, $entry]), [
                'car_ids' => [$car->id],
            ])
            ->assertForbidden();
    }

    public function test_driver_payment_can_be_assigned_chassis(): void
    {
        $user = $this->manager();
        $company = $this->makeCompany('Driver Co');
        $payment = LandDriverPayment::query()->create([
            'company_id' => $company->id,
            'driver_name' => 'Ali',
            'cars_count' => 1,
            'type' => LandDriverPaymentType::Freight,
            'payment_date' => '2026-08-19',
            'amount' => 10,
            'currency' => Currency::USD,
            'cash_account_id' => Account::query()->where('code', '1100')->value('id'),
            'created_by' => $user->id,
        ]);
        $car = $this->addCar($this->makeTrip($company, $user, 'CMR-D'), 'WVWZZZ3CZWE999999');

        $this->actingAs($user)
            ->post(route('land-trips.companies.driver-payments.chassis', [$company, $payment]), [
                'car_ids' => [$car->id],
            ])
            ->assertRedirect();

        $this->assertSame('WVWZZZ3CZWE999999', LandPaymentChassis::query()->value('chassis_no'));
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

    private function makeWalletEntry(Company $company, User $user): CompanyWalletEntry
    {
        return CompanyWalletEntry::query()->create([
            'company_id' => $company->id,
            'voucher_number' => 'W-'.$company->id.'-'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT).'-T',
            'type' => CompanyWalletEntryType::Deposit,
            'amount' => 100,
            'currency' => Currency::USD,
            'created_by' => $user->id,
        ]);
    }
}
