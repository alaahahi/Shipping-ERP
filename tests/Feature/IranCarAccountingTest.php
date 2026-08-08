<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\IranBorder;
use App\Enums\IranCarStatus;
use App\Enums\JournalStatus;
use App\Enums\Permission;
use App\Models\Account;
use App\Models\IranCar;
use App\Models\IranCarPayment;
use App\Models\User;
use App\Services\CompanyService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IranCarAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_creating_a_car_posts_debit_to_1660_child_and_credit_to_4300(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $revenue = Account::query()->where('code', '4300')->firstOrFail();

        $this->actingAs($user)
            ->post(route('iran-cars.store'), [
                'company_id' => $company->id,
                'border' => IranBorder::AmirAbad->value,
                'model_name' => 'ELANTRA LUX PREMIUM',
                'year' => 2026,
                'color' => 'WHITE',
                'vin' => 'lbecn afd5tz567932',
                'total_amount' => 1500.50,
            ])
            ->assertRedirect();

        $car = IranCar::query()->firstOrFail();
        $this->assertSame('LBECNAFD5TZ567932', $car->vin);
        $this->assertNotNull($car->invoice_journal_id);
        $this->assertNotNull($company->fresh()->iran_ar_account_id);
        $this->assertNotSame($company->ar_account_id, $company->fresh()->iran_ar_account_id);

        $iranAr = $company->fresh('iranArAccount')->iranArAccount;
        $this->assertSame('1660-'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT), $iranAr?->code);
        $this->assertSame($this->controlIranAr()->id, $iranAr?->parent_id);
        $this->assertNotSame($this->controlShippingAr()->id, $iranAr?->parent_id);

        $entry = $car->invoiceJournal()->with('lines')->firstOrFail();
        $this->assertSame(JournalStatus::Posted, $entry->status);
        $this->assertEquals(1500.50, (float) $entry->lines->sum('debit'));
        $this->assertEquals(1500.50, (float) $entry->lines->sum('credit'));

        $debit = $entry->lines->firstWhere('debit', '>', 0);
        $credit = $entry->lines->firstWhere('credit', '>', 0);

        $this->assertSame($iranAr->id, $debit?->account_id);
        $this->assertSame($company->id, $debit?->company_id);
        $this->assertSame($revenue->id, $credit?->account_id);
        $this->assertNotSame($company->ar_account_id, $debit?->account_id);
        $this->assertSame('1500.50', $car->remainingAmount());
    }

    public function test_payment_posts_debit_cash_credit_1660_and_reduces_remaining(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)
            ->post(route('iran-cars.store'), [
                'company_id' => $company->id,
                'border' => IranBorder::Jolfa->value,
                'model_name' => 'TOYOTA CAMRY',
                'vin' => 'LVGBECEK3TG129171',
                'total_amount' => 1000,
            ])
            ->assertRedirect();

        $car = IranCar::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('iran-cars.payments.store', $car), [
                'payment_date' => '2026-08-08',
                'amount' => 400,
                'debit_account_id' => $cash->id,
                'reference' => 'CASH-1',
            ])
            ->assertRedirect(route('iran-cars.show', $car));

        $payment = IranCarPayment::query()->firstOrFail();
        $this->assertSame('ICP-'.now()->format('Ym').'-0001', $payment->voucher_number);
        $this->assertNotNull($payment->journal_entry_id);

        $entry = $payment->journalEntry()->with('lines')->firstOrFail();
        $this->assertSame(JournalStatus::Posted, $entry->status);

        $debit = $entry->lines->firstWhere('debit', '>', 0);
        $credit = $entry->lines->firstWhere('credit', '>', 0);
        $iranAr = $company->fresh()->iranArAccount;

        $this->assertSame($cash->id, $debit?->account_id);
        $this->assertSame($iranAr->id, $credit?->account_id);
        $this->assertSame($company->id, $credit?->company_id);
        $this->assertNotSame($company->ar_account_id, $credit?->account_id);

        $car = $car->fresh();
        $this->assertSame(400.0, $car->paidAmount());
        $this->assertSame(600.0, $car->remainingAmount());
        $this->assertSame(IranCarStatus::Open, $car->status);
    }

    public function test_overpayment_is_rejected(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)
            ->post(route('iran-cars.store'), [
                'company_id' => $company->id,
                'border' => IranBorder::Bazargan->value,
                'model_name' => 'TOYOTA RAV4',
                'vin' => 'LFMJBFBF8T3010035',
                'total_amount' => 200,
            ]);

        $car = IranCar::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('iran-cars.show', $car))
            ->post(route('iran-cars.payments.store', $car), [
                'payment_date' => '2026-08-08',
                'amount' => 200.01,
                'debit_account_id' => $cash->id,
            ])
            ->assertRedirect(route('iran-cars.show', $car))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, IranCarPayment::query()->count());
        $this->assertSame(200.0, $car->fresh()->remainingAmount());
    }

    public function test_full_payment_marks_car_paid(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)->post(route('iran-cars.store'), [
            'company_id' => $company->id,
            'border' => IranBorder::AmirAbad->value,
            'model_name' => 'MG 5',
            'vin' => 'LSJA36U32SZ041228',
            'total_amount' => 14000,
        ]);

        $car = IranCar::query()->firstOrFail();

        $this->actingAs($user)->post(route('iran-cars.payments.store', $car), [
            'payment_date' => '2026-08-08',
            'amount' => 14000,
            'debit_account_id' => $cash->id,
        ])->assertRedirect();

        $this->assertSame(IranCarStatus::Paid, $car->fresh()->status);
        $this->assertSame(0.0, $car->fresh()->remainingAmount());
    }

    public function test_unauthorized_user_cannot_create_iran_car(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::VoyagesView->value);
        $company = $this->makeCompany();

        $this->actingAs($user)
            ->post(route('iran-cars.store'), [
                'company_id' => $company->id,
                'border' => IranBorder::AmirAbad->value,
                'model_name' => 'ELANTRA',
                'vin' => 'LBECNAFD1SZ551810',
                'total_amount' => 10,
            ])
            ->assertForbidden();

        $this->assertSame(0, IranCar::query()->count());
    }

    private function iranCarsUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::IranCarsView->value,
            Permission::IranCarsManage->value,
        ]);

        return $user;
    }

    private function makeCompany()
    {
        return app(CompanyService::class)->create([
            'name' => 'Iran Motors Co',
            'is_active' => true,
        ]);
    }

    private function controlIranAr(): Account
    {
        return Account::query()->where('code', '1660')->firstOrFail();
    }

    private function controlShippingAr(): Account
    {
        return Account::query()->where('code', '1600')->firstOrFail();
    }
}
