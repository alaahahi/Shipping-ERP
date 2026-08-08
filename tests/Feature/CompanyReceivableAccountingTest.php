<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\JournalStatus;
use App\Enums\MoneyVoucherType;
use App\Enums\Permission;
use App\Enums\VoyageStatus;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyDirectCharge;
use App\Models\JournalLine;
use App\Models\Ship;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VoyageCar;
use App\Models\VoyageCompany;
use App\Services\CompanyDirectChargeService;
use App\Services\CompanyLedgerService;
use App\Services\CompanyService;
use App\Services\JournalService;
use App\Services\MoneyVoucherService;
use App\Services\VoyageSettlementPostingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyReceivableAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_creating_a_company_upserts_child_ar_account_under_1600(): void
    {
        $company = app(CompanyService::class)->create([
            'name' => 'Al Noor Shipping',
            'is_active' => true,
        ]);

        $control = Account::query()->where('code', '1600')->firstOrFail();

        $this->assertNotNull($company->ar_account_id);
        $this->assertSame($control->id, $company->arAccount?->parent_id);
        $this->assertSame('Al Noor Shipping', $company->arAccount?->name);
        $this->assertSame('1600-'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT), $company->arAccount?->code);
        $this->assertFalse((bool) $company->arAccount?->is_system);
        $this->assertSame(Company::class, $company->arAccount?->accountable_type);
        $this->assertSame($company->id, $company->arAccount?->accountable_id);
    }

    public function test_renaming_a_company_updates_the_ar_account_name(): void
    {
        $service = app(CompanyService::class);
        $company = $service->create([
            'name' => 'Old Name Co',
            'is_active' => true,
        ]);

        $service->update($company, [
            'name' => 'New Name Co',
            'is_active' => true,
        ]);

        $this->assertSame('New Name Co', $company->fresh('arAccount')->arAccount?->name);
    }

    public function test_direct_receivable_posts_balanced_journal_to_company_ar_and_4100(): void
    {
        $user = $this->accountingUser();
        $company = app(CompanyService::class)->create([
            'name' => 'Gulf Traders',
            'is_active' => true,
        ]);
        $revenue = Account::query()->where('code', '4100')->firstOrFail();

        $this->actingAs($user)
            ->post(route('companies.direct-charges.store', $company), [
                'charge_date' => '2026-08-08',
                'amount' => 250.50,
                'currency' => Currency::USD->value,
                'credit_account_id' => $revenue->id,
                'reference' => 'OPEN-1',
                'description' => 'Opening receivable without voyage',
            ])
            ->assertRedirect(route('companies.show', $company));

        $charge = CompanyDirectCharge::query()->firstOrFail();
        $this->assertSame('CDC-'.now()->format('Ym').'-0001', $charge->voucher_number);
        $this->assertNotNull($charge->journal_entry_id);
        $this->assertSame($user->id, $charge->created_by);

        $entry = $charge->journalEntry()->with('lines')->firstOrFail();
        $this->assertSame(JournalStatus::Posted, $entry->status);
        $this->assertEquals(250.50, (float) $entry->lines->sum('debit'));
        $this->assertEquals(250.50, (float) $entry->lines->sum('credit'));

        $debit = $entry->lines->firstWhere('debit', '>', 0);
        $credit = $entry->lines->firstWhere('credit', '>', 0);

        $this->assertSame($company->ar_account_id, $debit?->account_id);
        $this->assertSame($company->id, $debit?->company_id);
        $this->assertSame($revenue->id, $credit?->account_id);
        $this->assertNotSame($this->controlAr()->id, $debit?->account_id);
    }

    public function test_company_ledger_includes_historical_1600_lines_and_new_child_ar(): void
    {
        $user = $this->accountingUser();
        $company = app(CompanyService::class)->create([
            'name' => 'Legacy Mix Co',
            'is_active' => true,
        ]);

        $this->postHistoricalControlAr($company, $user, 100);

        app(CompanyDirectChargeService::class)->createAndPost($company, [
            'charge_date' => '2026-08-08',
            'amount' => 40,
            'currency' => Currency::USD->value,
            'description' => 'Extra direct debt',
        ], $user);

        $ledger = app(CompanyLedgerService::class)->statement($company->fresh());

        $this->assertSame('140.00', $ledger['open_balance']);
        $this->assertSame('140.00', $ledger['total_debit']);
        $this->assertSame('0.00', $ledger['total_credit']);
        $this->assertCount(2, $ledger['movements']);
    }

    public function test_receipt_voucher_credits_company_ar_child_not_control_1600(): void
    {
        $user = $this->accountingUser();
        $company = app(CompanyService::class)->create([
            'name' => 'Cash Customer',
            'is_active' => true,
        ]);
        $cash = Account::query()->where('code', '1100')->firstOrFail();

        app(CompanyDirectChargeService::class)->createAndPost($company, [
            'charge_date' => '2026-08-01',
            'amount' => 80,
            'currency' => Currency::USD->value,
            'description' => 'Charge before collection',
        ], $user);

        $voucherService = app(MoneyVoucherService::class);
        $voucher = $voucherService->create([
            'type' => MoneyVoucherType::Receipt->value,
            'voucher_date' => '2026-08-08',
            'currency' => Currency::USD->value,
            'amount' => 80,
            'payment_account_id' => $cash->id,
            'company_id' => $company->id,
            'description' => 'Collection',
        ], $user);

        $posted = $voucherService->post($voucher, $user);
        $entry = $posted->journalEntry()->with('lines')->firstOrFail();

        $credit = $entry->lines->firstWhere('credit', '>', 0);
        $this->assertSame($company->fresh()->ar_account_id, $credit?->account_id);
        $this->assertSame($company->id, $credit?->company_id);
        $this->assertNotSame($this->controlAr()->id, $credit?->account_id);

        $ledger = app(CompanyLedgerService::class)->statement($company->fresh());
        $this->assertSame('0.00', $ledger['open_balance']);
    }

    public function test_voyage_revenue_posts_debit_to_company_ar_child(): void
    {
        $user = $this->accountingUser();
        $company = app(CompanyService::class)->create([
            'name' => 'Voyage Client',
            'is_active' => true,
        ]);

        $ship = Ship::query()->create([
            'name' => 'Test Vessel',
            'is_active' => true,
        ]);

        $voyage = Voyage::query()->create([
            'ship_id' => $ship->id,
            'voyage_number' => 'V-100',
            'sailing_date' => '2026-08-01',
            'status' => VoyageStatus::Active,
        ]);

        $voyageCompany = VoyageCompany::query()->create([
            'voyage_id' => $voyage->id,
            'company_id' => $company->id,
            'company_name' => $company->name,
            'shipping_price_per_car' => 200,
            'clearance_per_car' => 50,
        ]);

        VoyageCar::query()->create([
            'voyage_id' => $voyage->id,
            'voyage_company_id' => $voyageCompany->id,
            'consignee_name' => 'Buyer',
            'chassis_no' => 'CHS-1',
        ]);

        app(VoyageSettlementPostingService::class)->postRevenue($voyage->fresh(), $user);

        $line = JournalLine::query()
            ->where('company_id', $company->id)
            ->where('voyage_id', $voyage->id)
            ->where('debit', '>', 0)
            ->firstOrFail();

        $this->assertSame($company->fresh()->ar_account_id, $line->account_id);
        $this->assertEquals(250.0, (float) $line->debit);
        $this->assertNotSame($this->controlAr()->id, $line->account_id);
    }

    public function test_unauthorized_user_cannot_post_direct_receivable(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::VoyagesView->value);

        $company = app(CompanyService::class)->create([
            'name' => 'Locked Co',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('companies.direct-charges.store', $company), [
                'charge_date' => '2026-08-08',
                'amount' => 10,
                'currency' => Currency::USD->value,
                'description' => 'Should fail',
            ])
            ->assertForbidden();

        $this->assertSame(0, CompanyDirectCharge::query()->count());
    }

    private function accountingUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::AccountingView->value,
            Permission::AccountingManage->value,
            Permission::VoyagesView->value,
            Permission::VoyagesManage->value,
        ]);

        return $user;
    }

    private function controlAr(): Account
    {
        return Account::query()->where('code', '1600')->firstOrFail();
    }

    private function postHistoricalControlAr(Company $company, User $user, float $amount): void
    {
        $control = $this->controlAr();
        $revenue = Account::query()->where('code', '4100')->firstOrFail();

        $draft = app(JournalService::class)->createDraft([
            'entry_date' => '2026-07-01',
            'currency' => Currency::USD->value,
            'reference' => 'LEGACY-AR',
            'description' => 'Historical control AR',
            'lines' => [
                [
                    'account_id' => $control->id,
                    'company_id' => $company->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Legacy 1600',
                ],
                [
                    'account_id' => $revenue->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Legacy revenue',
                ],
            ],
        ], $user);

        app(JournalService::class)->post($draft, $user);
    }
}
