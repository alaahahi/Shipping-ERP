<?php

namespace Tests\Feature;

use App\Enums\IranBorder;
use App\Enums\IranCarSaleState;
use App\Enums\IranCarStatus;
use App\Enums\JournalStatus;
use App\Enums\Permission;
use App\Models\Account;
use App\Models\IranCar;
use App\Models\IranCarPayment;
use App\Models\IranCarPoolPayment;
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

    public function test_creating_an_unsold_car_does_not_post_a_journal(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();

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
        $this->assertSame(IranCarSaleState::Unsold, $car->sale_state);
        $this->assertNull($car->invoice_journal_id);
        $this->assertSame(0.0, $car->remainingAmount());
        $this->assertNull($company->fresh()->iran_ar_account_id);
    }

    public function test_marking_sold_posts_debit_to_1660_child_and_credit_to_4300(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $revenue = Account::query()->where('code', '4300')->firstOrFail();
        $car = $this->createUnsoldCar($user, $company, 1200);

        $this->actingAs($user)
            ->post(route('iran-cars.sell', $car), [
                'sale_price' => 1500.50,
                'sold_at' => '2026-08-08',
            ])
            ->assertRedirect(route('iran-cars.show', $car));

        $car = $car->fresh();
        $this->assertSame(IranCarSaleState::Sold, $car->sale_state);
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
        $this->assertEquals(1500.50, $car->remainingAmount());
    }

    public function test_payment_is_rejected_on_unsold_cars(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $car = $this->createUnsoldCar($user, $company, 1000);

        $this->actingAs($user)
            ->from(route('iran-cars.show', $car))
            ->post(route('iran-cars.payments.store', $car), [
                'payment_date' => '2026-08-08',
                'amount' => 400,
                'debit_account_id' => $cash->id,
            ])
            ->assertRedirect(route('iran-cars.show', $car))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, IranCarPayment::query()->count());
    }

    public function test_payment_posts_debit_cash_credit_1660_and_reduces_remaining(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $car = $this->createSoldCar($user, $company, 1000);

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

        $car = $car->fresh();
        $this->assertSame(400.0, $car->paidAmount());
        $this->assertSame(600.0, $car->remainingAmount());
        $this->assertSame(IranCarStatus::Open, $car->status);
    }

    public function test_sale_price_can_be_edited_before_payments(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $car = $this->createSoldCar($user, $company, 1000);

        $this->actingAs($user)
            ->put(route('iran-cars.update', $car), [
                'company_id' => $company->id,
                'border' => $car->border->value,
                'model_name' => $car->model_name,
                'vin' => $car->vin,
                'total_amount' => $car->total_amount,
                'sale_price' => 1800,
            ])
            ->assertRedirect(route('iran-cars.show', $car));

        $car = $car->fresh('invoiceJournal.lines');
        $this->assertEquals(1800.0, (float) $car->sale_price);
        $this->assertEquals(1800.0, (float) $car->invoiceJournal?->lines->sum('debit'));
        $this->assertEquals(1800.0, $car->remainingAmount());
    }

    public function test_overpayment_is_rejected(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $car = $this->createSoldCar($user, $company, 200);

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
        $car = $this->createSoldCar($user, $company, 14000);

        $this->actingAs($user)->post(route('iran-cars.payments.store', $car), [
            'payment_date' => '2026-08-08',
            'amount' => 14000,
            'debit_account_id' => $cash->id,
        ])->assertRedirect();

        $this->assertSame(IranCarStatus::Paid, $car->fresh()->status);
        $this->assertSame(0.0, $car->fresh()->remainingAmount());
    }

    public function test_pool_payment_reduces_global_remaining_without_car_id(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $carA = $this->createSoldCar($user, $company, 1000);
        $carB = $this->createSoldCar($user, $company, 500);

        $this->actingAs($user)
            ->post(route('iran-cars.pool-payments.store'), [
                'company_id' => $company->id,
                'payment_date' => '2026-08-08',
                'amount' => 700,
                'debit_account_id' => $cash->id,
                'reference' => 'POOL-1',
            ])
            ->assertRedirect(route('iran-cars.index', ['sale_state' => IranCarSaleState::Sold->value]));

        $payment = IranCarPoolPayment::query()->firstOrFail();
        $this->assertSame('ICPP-'.now()->format('Ym').'-0001', $payment->voucher_number);
        $this->assertSame($company->id, $payment->company_id);
        $this->assertNull(IranCarPayment::query()->first());

        $entry = $payment->journalEntry()->with('lines')->firstOrFail();
        $this->assertSame(JournalStatus::Posted, $entry->status);
        $debit = $entry->lines->firstWhere('debit', '>', 0);
        $credit = $entry->lines->firstWhere('credit', '>', 0);
        $iranAr = $company->fresh()->iranArAccount;

        $this->assertSame($cash->id, $debit?->account_id);
        $this->assertSame($iranAr->id, $credit?->account_id);
        $this->assertSame($company->id, $credit?->company_id);

        $summary = app(\App\Services\IranCarService::class)->globalPaymentSummary();
        $this->assertSame(1500.0, $summary['billed']);
        $this->assertSame(0.0, $summary['car_paid']);
        $this->assertSame(700.0, $summary['pool_paid']);
        $this->assertSame(800.0, $summary['remaining']);

        $this->assertSame(1000.0, $carA->fresh()->remainingAmount());
        $this->assertSame(500.0, $carB->fresh()->remainingAmount());
    }

    public function test_pool_overpayment_is_rejected(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $this->createSoldCar($user, $company, 200);

        $this->actingAs($user)
            ->from(route('iran-cars.index', ['sale_state' => IranCarSaleState::Sold->value]))
            ->post(route('iran-cars.pool-payments.store'), [
                'company_id' => $company->id,
                'payment_date' => '2026-08-08',
                'amount' => 200.01,
                'debit_account_id' => $cash->id,
            ])
            ->assertRedirect(route('iran-cars.index', ['sale_state' => IranCarSaleState::Sold->value]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, IranCarPoolPayment::query()->count());
    }

    public function test_car_and_pool_payments_combine_for_global_remaining(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $car = $this->createSoldCar($user, $company, 1000);

        $this->actingAs($user)->post(route('iran-cars.payments.store', $car), [
            'payment_date' => '2026-08-08',
            'amount' => 300,
            'debit_account_id' => $cash->id,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('iran-cars.pool-payments.store'), [
            'company_id' => $company->id,
            'payment_date' => '2026-08-08',
            'amount' => 400,
            'debit_account_id' => $cash->id,
        ])->assertRedirect();

        $summary = app(\App\Services\IranCarService::class)->globalPaymentSummary();
        $this->assertSame(1000.0, $summary['billed']);
        $this->assertSame(300.0, $summary['car_paid']);
        $this->assertSame(400.0, $summary['pool_paid']);
        $this->assertSame(300.0, $summary['remaining']);
        $this->assertSame(700.0, $car->fresh()->remainingAmount());
        $this->assertSame(IranCarStatus::Open, $car->fresh()->status);
    }

    public function test_full_pool_payment_marks_sold_cars_paid(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $carA = $this->createSoldCar($user, $company, 600);
        $carB = $this->createSoldCar($user, $company, 400);

        $this->actingAs($user)->post(route('iran-cars.pool-payments.store'), [
            'company_id' => $company->id,
            'payment_date' => '2026-08-08',
            'amount' => 1000,
            'debit_account_id' => $cash->id,
        ])->assertRedirect();

        $this->assertSame(IranCarStatus::Paid, $carA->fresh()->status);
        $this->assertSame(IranCarStatus::Paid, $carB->fresh()->status);
        $this->assertSame(0.0, app(\App\Services\IranCarService::class)->globalPaymentSummary()['remaining']);
    }

    public function test_reversing_pool_payment_restores_remaining_and_voids_journal(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $car = $this->createSoldCar($user, $company, 800);

        $this->actingAs($user)->post(route('iran-cars.pool-payments.store'), [
            'company_id' => $company->id,
            'payment_date' => '2026-08-08',
            'amount' => 500,
            'debit_account_id' => $cash->id,
        ])->assertRedirect();

        $payment = IranCarPoolPayment::query()->firstOrFail();
        $journalId = $payment->journal_entry_id;

        $this->actingAs($user)
            ->delete(route('iran-cars.pool-payments.destroy', $payment))
            ->assertRedirect(route('iran-cars.index', ['sale_state' => IranCarSaleState::Sold->value]));

        $this->assertSoftDeleted($payment);
        $this->assertSame(JournalStatus::Void, \App\Models\JournalEntry::query()->findOrFail($journalId)->status);
        $this->assertSame(800.0, app(\App\Services\IranCarService::class)->globalPaymentSummary()['remaining']);
        $this->assertSame(IranCarStatus::Open, $car->fresh()->status);
    }

    public function test_car_payment_cannot_exceed_global_remaining_after_pool(): void
    {
        $user = $this->iranCarsUser();
        $company = $this->makeCompany();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $car = $this->createSoldCar($user, $company, 1000);

        $this->actingAs($user)->post(route('iran-cars.pool-payments.store'), [
            'company_id' => $company->id,
            'payment_date' => '2026-08-08',
            'amount' => 700,
            'debit_account_id' => $cash->id,
        ])->assertRedirect();

        $this->actingAs($user)
            ->from(route('iran-cars.show', $car))
            ->post(route('iran-cars.payments.store', $car), [
                'payment_date' => '2026-08-08',
                'amount' => 400,
                'debit_account_id' => $cash->id,
            ])
            ->assertRedirect(route('iran-cars.show', $car))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, IranCarPayment::query()->count());
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

    private function createUnsoldCar(User $user, $company, float $listPrice): IranCar
    {
        $this->actingAs($user)->post(route('iran-cars.store'), [
            'company_id' => $company->id,
            'border' => IranBorder::Jolfa->value,
            'model_name' => 'TOYOTA CAMRY',
            'vin' => 'LVGBECEK3TG'.str_pad((string) random_int(100000, 999999), 6, '0'),
            'total_amount' => $listPrice,
        ])->assertRedirect();

        return IranCar::query()->latest('id')->firstOrFail();
    }

    private function createSoldCar(User $user, $company, float $salePrice): IranCar
    {
        $car = $this->createUnsoldCar($user, $company, $salePrice);

        $this->actingAs($user)->post(route('iran-cars.sell', $car), [
            'sale_price' => $salePrice,
            'sold_at' => '2026-08-08',
        ])->assertRedirect();

        return $car->fresh();
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
