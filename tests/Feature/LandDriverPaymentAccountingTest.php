<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\JournalStatus;
use App\Enums\Permission;
use App\Enums\SettingKey;
use App\Models\Account;
use App\Models\CompanyWalletEntry;
use App\Models\LandDriverPayment;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\SettingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandDriverPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_driver_payment_posts_debit_company_ar_and_credit_cash(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany();
        $cash = $this->cashAccount();
        $this->setCashAccount($cash);

        $this->actingAs($user)
            ->post(route('land-trips.companies.driver-payments.store', $company), [
                'driver_name' => 'Ahmed Driver',
                'cmr_number' => 'CMR-90',
                'cars_count' => 3,
                'type' => 'freight',
                'payment_date' => '2026-08-18',
                'amount' => 150.25,
            ])
            ->assertRedirect();

        $payment = LandDriverPayment::query()->firstOrFail();
        $this->assertSame($company->id, $payment->company_id);
        $this->assertSame('Ahmed Driver', $payment->driver_name);
        $this->assertSame($cash->id, $payment->cash_account_id);
        $this->assertNotNull($payment->journal_entry_id);

        $entry = $payment->journalEntry()->with('lines')->firstOrFail();
        $this->assertSame(JournalStatus::Posted, $entry->status);
        $this->assertEquals(150.25, (float) $entry->lines->sum('debit'));
        $this->assertEquals(150.25, (float) $entry->lines->sum('credit'));

        $debit = $entry->lines->firstWhere('debit', '>', 0);
        $credit = $entry->lines->firstWhere('credit', '>', 0);

        $this->assertSame($company->fresh()->ar_account_id, $debit?->account_id);
        $this->assertSame($company->id, $debit?->company_id);
        $this->assertSame($cash->id, $credit?->account_id);
        $this->assertEquals(0.0, (float) $credit?->debit);
    }

    public function test_wallet_deposit_posts_debit_cash_and_credit_company_ar(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany();
        $cash = $this->cashAccount();
        $this->setCashAccount($cash);

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.store', $company), [
                'type' => 'deposit',
                'amount' => 80,
                'currency' => Currency::USD->value,
                'notes' => 'Company paid',
            ])
            ->assertRedirect();

        $wallet = CompanyWalletEntry::query()->firstOrFail();
        $this->assertNotNull($wallet->journal_entry_id);

        $entry = $wallet->journalEntry()->with('lines')->firstOrFail();
        $this->assertSame(JournalStatus::Posted, $entry->status);

        $debit = $entry->lines->firstWhere('debit', '>', 0);
        $credit = $entry->lines->firstWhere('credit', '>', 0);

        $this->assertSame($cash->id, $debit?->account_id);
        $this->assertSame($company->fresh()->ar_account_id, $credit?->account_id);
        $this->assertSame($company->id, $credit?->company_id);
    }

    public function test_wallet_withdraw_posts_debit_company_ar_and_credit_cash(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany();
        $cash = $this->cashAccount();
        $this->setCashAccount($cash);

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.store', $company), [
                'type' => 'deposit',
                'amount' => 100,
                'currency' => Currency::USD->value,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.store', $company), [
                'type' => 'withdraw',
                'amount' => 40,
                'currency' => Currency::USD->value,
            ])
            ->assertRedirect();

        $withdraw = CompanyWalletEntry::query()->where('type', 'withdraw')->firstOrFail();
        $entry = $withdraw->journalEntry()->with('lines')->firstOrFail();

        $debit = $entry->lines->firstWhere('debit', '>', 0);
        $credit = $entry->lines->firstWhere('credit', '>', 0);

        $this->assertSame($company->fresh()->ar_account_id, $debit?->account_id);
        $this->assertSame($company->id, $debit?->company_id);
        $this->assertSame($cash->id, $credit?->account_id);
    }

    public function test_wallet_deposit_stores_attachment_and_links_it_to_the_journal(): void
    {
        Storage::fake('public');

        $user = $this->landTripsUser();
        $company = $this->makeCompany();
        $this->setCashAccount($this->cashAccount());
        $file = UploadedFile::fake()->image('receipt.jpg');

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.store', $company), [
                'type' => 'deposit',
                'amount' => 25,
                'currency' => Currency::USD->value,
                'attachment' => $file,
            ])
            ->assertRedirect();

        $wallet = CompanyWalletEntry::query()->firstOrFail();
        $this->assertNotNull($wallet->attachment_path);
        $this->assertSame('receipt.jpg', $wallet->attachment_original_name);
        Storage::disk('public')->assertExists($wallet->attachment_path);
        $this->assertSame($wallet->attachment_path, $wallet->journalEntry?->attachment_path);

        $this->actingAs($user)
            ->get(route('land-trips.companies.wallet.attachment', [$company, $wallet]))
            ->assertOk();
    }

    public function test_driver_payment_stores_attachment_and_links_it_to_the_journal(): void
    {
        Storage::fake('public');

        $user = $this->landTripsUser();
        $company = $this->makeCompany();
        $this->setCashAccount($this->cashAccount());
        $file = UploadedFile::fake()->create('voucher.pdf', 80, 'application/pdf');

        $this->actingAs($user)
            ->post(route('land-trips.companies.driver-payments.store', $company), [
                'driver_name' => 'Sami Driver',
                'cars_count' => 2,
                'type' => 'freight',
                'payment_date' => '2026-08-18',
                'amount' => 40,
                'attachment' => $file,
            ])
            ->assertRedirect();

        $payment = LandDriverPayment::query()->firstOrFail();
        $this->assertNotNull($payment->attachment_path);
        $this->assertSame('voucher.pdf', $payment->attachment_original_name);
        Storage::disk('public')->assertExists($payment->attachment_path);
        $this->assertSame($payment->attachment_path, $payment->journalEntry?->attachment_path);

        $this->actingAs($user)
            ->get(route('land-trips.companies.driver-payments.attachment', [$company, $payment]))
            ->assertOk();
    }

    public function test_missing_cash_account_setting_fails_validation(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany();

        $this->actingAs($user)
            ->post(route('land-trips.companies.driver-payments.store', $company), [
                'driver_name' => 'No Cash Driver',
                'cars_count' => 1,
                'type' => 'freight',
                'payment_date' => '2026-08-18',
                'amount' => 10,
            ])
            ->assertSessionHasErrors('cash_account_id');

        $this->assertSame(0, LandDriverPayment::query()->count());

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.store', $company), [
                'type' => 'deposit',
                'amount' => 10,
                'currency' => Currency::USD->value,
            ])
            ->assertSessionHasErrors('cash_account_id');

        $this->assertSame(0, CompanyWalletEntry::query()->count());
    }

    public function test_deleting_driver_payment_voids_journal(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany();
        $this->setCashAccount($this->cashAccount());

        $this->actingAs($user)
            ->post(route('land-trips.companies.driver-payments.store', $company), [
                'driver_name' => 'Delete Me',
                'cars_count' => 2,
                'type' => 'commission',
                'payment_date' => '2026-08-18',
                'amount' => 25,
            ])
            ->assertRedirect();

        $payment = LandDriverPayment::query()->firstOrFail();
        $journalId = $payment->journal_entry_id;

        $this->actingAs($user)
            ->delete(route('land-trips.companies.driver-payments.destroy', [$company, $payment]))
            ->assertRedirect();

        $this->assertSoftDeleted($payment);
        $this->assertSame(JournalStatus::Void, $payment->journalEntry()->first()?->status);
        $this->assertNotNull($journalId);
    }

    public function test_deleting_wallet_entry_voids_journal(): void
    {
        $user = $this->landTripsUser();
        $company = $this->makeCompany();
        $this->setCashAccount($this->cashAccount());

        $this->actingAs($user)
            ->post(route('land-trips.companies.wallet.store', $company), [
                'type' => 'deposit',
                'amount' => 50,
                'currency' => Currency::USD->value,
            ])
            ->assertRedirect();

        $entry = CompanyWalletEntry::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('land-trips.companies.wallet.destroy', [$company, $entry]))
            ->assertRedirect();

        $this->assertSoftDeleted($entry);
        $this->assertSame(JournalStatus::Void, $entry->journalEntry()->first()?->status);
    }

    public function test_unauthorized_user_cannot_post_driver_payment(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::LandTripsView->value);

        $company = $this->makeCompany();
        $this->setCashAccount($this->cashAccount());

        $this->actingAs($user)
            ->post(route('land-trips.companies.driver-payments.store', $company), [
                'driver_name' => 'Blocked',
                'cars_count' => 1,
                'type' => 'freight',
                'payment_date' => '2026-08-18',
                'amount' => 10,
            ])
            ->assertForbidden();

        $this->assertSame(0, LandDriverPayment::query()->count());
    }

    private function landTripsUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::LandTripsView->value,
            Permission::LandTripsManage->value,
        ]);

        return $user;
    }

    private function makeCompany()
    {
        return app(CompanyService::class)->create([
            'name' => 'Land Transit Co',
            'is_active' => true,
        ]);
    }

    private function cashAccount(): Account
    {
        return Account::query()->where('code', '1100')->firstOrFail();
    }

    private function setCashAccount(Account $account): void
    {
        app(SettingService::class)->updateMany([
            SettingKey::LandTripsCashAccountId->value => (string) $account->id,
        ]);
    }
}
