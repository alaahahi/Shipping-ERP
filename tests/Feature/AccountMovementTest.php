<?php

namespace Tests\Feature;

use App\Enums\JournalStatus;
use App\Enums\Permission;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountMovementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_receipt_debits_current_account_and_credits_counterpart(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.movements.store', $cash), [
                'type' => 'receipt',
                'counterpart_account_id' => $bank->id,
                'amount' => 150.5,
                'entry_date' => '2026-08-15',
                'description' => 'Cash in from bank',
            ])
            ->assertRedirect(route('accounts.show', $cash));

        $entry = JournalEntry::query()->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertSame(JournalStatus::Posted, $entry->status);
        $this->assertSame('Cash in from bank', $entry->description);

        $this->assertEquals(150.5, (float) JournalLine::query()->where('journal_entry_id', $entry->id)->where('account_id', $cash->id)->value('debit'));
        $this->assertEquals(150.5, (float) JournalLine::query()->where('journal_entry_id', $entry->id)->where('account_id', $bank->id)->value('credit'));
    }

    public function test_payment_credits_current_account_and_debits_counterpart(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.movements.store', $cash), [
                'type' => 'payment',
                'counterpart_account_id' => $bank->id,
                'amount' => 80,
                'entry_date' => '2026-08-15',
            ])
            ->assertRedirect(route('accounts.show', $cash));

        $entry = JournalEntry::query()->latest('id')->first();
        $this->assertEquals(80.0, (float) JournalLine::query()->where('journal_entry_id', $entry->id)->where('account_id', $cash->id)->value('credit'));
        $this->assertEquals(80.0, (float) JournalLine::query()->where('journal_entry_id', $entry->id)->where('account_id', $bank->id)->value('debit'));
    }

    public function test_ledger_running_balance_does_not_double_count_unfiltered_payments(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        foreach ([1000, 1000] as $amount) {
            $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
                'type' => 'payment',
                'counterpart_account_id' => $bank->id,
                'amount' => $amount,
                'entry_date' => '2026-08-15',
            ])->assertRedirect();
        }

        $this->actingAs($user)
            ->get(route('accounts.show', $cash))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('account.balance', '-2000.00')
                ->where('period_debit', '0.00')
                ->where('period_credit', '2000.00')
                ->where('period_net', '2000.00')
                ->where('lines.data.0.balance', '-1000.00')
                ->where('lines.data.1.balance', '-2000.00')
                ->where('lines.data.0.counterpart.code', $bank->code)
                ->where('lines.data.0.counterpart.name', $bank->name)
            );
    }

    public function test_ledger_can_filter_by_description_and_amount(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
            'type' => 'payment',
            'counterpart_account_id' => $bank->id,
            'amount' => 1000,
            'entry_date' => '2026-08-15',
            'description' => 'Office rent',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
            'type' => 'payment',
            'counterpart_account_id' => $bank->id,
            'amount' => 250,
            'entry_date' => '2026-08-15',
            'description' => 'Supplies',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('accounts.show', ['account' => $cash, 'description' => 'rent', 'amount' => 1000]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('lines.total', 1)
                ->where('lines.data.0.description', 'Office rent')
            );
    }

    public function test_viewer_cannot_post_movement(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::AccountingView->value);
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.movements.store', $cash), [
                'type' => 'receipt',
                'counterpart_account_id' => $bank->id,
                'amount' => 10,
                'entry_date' => '2026-08-15',
            ])
            ->assertForbidden();
    }

    public function test_posted_movement_can_update_description_and_attachment_without_changing_amounts(): void
    {
        Storage::fake('public');
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
            'type' => 'receipt',
            'counterpart_account_id' => $bank->id,
            'amount' => 40,
            'entry_date' => '2026-08-15',
            'description' => 'Original',
        ])->assertRedirect();

        $entry = JournalEntry::query()->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('accounts.journals.update', [$cash, $entry]), [
            'description' => 'Updated note',
            'attachment' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect(route('accounts.show', $cash));

        $entry->refresh();
        $this->assertSame('Updated note', $entry->description);
        $this->assertNotNull($entry->attachment_path);
        $this->assertEquals(40.0, (float) JournalLine::query()->where('journal_entry_id', $entry->id)->where('account_id', $cash->id)->value('debit'));
        Storage::disk('public')->assertExists($entry->attachment_path);
    }

    public function test_voiding_a_movement_removes_it_from_the_posted_ledger(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
            'type' => 'payment',
            'counterpart_account_id' => $bank->id,
            'amount' => 25,
            'entry_date' => '2026-08-15',
        ])->assertRedirect();

        $entry = JournalEntry::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.journals.void', [$cash, $entry]))
            ->assertRedirect(route('accounts.show', $cash));

        $entry->refresh();
        $this->assertSame(JournalStatus::Void, $entry->status);
        $this->actingAs($user)
            ->get(route('accounts.show', $cash))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('lines.total', 0));
    }

    public function test_viewer_can_print_posted_voucher(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::AccountingView->value);
        $manager = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($manager)->post(route('accounts.movements.store', $cash), [
            'type' => 'receipt',
            'counterpart_account_id' => $bank->id,
            'amount' => 40,
            'entry_date' => '2026-08-15',
            'description' => 'Printable receipt',
        ])->assertRedirect();

        $entry = JournalEntry::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('journals.print', $entry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Journals/Print')
                ->where('entry.voucher_number', $entry->voucher_number)
                ->where('entry.description', 'Printable receipt')
            );
    }

    public function test_ledger_excel_and_pdf_export_download(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.movements.store', $cash), [
                'type' => 'receipt',
                'counterpart_account_id' => $bank->id,
                'amount' => 25,
                'entry_date' => '2026-08-15',
                'description' => 'Exportable receipt',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('accounts.export.excel', $cash))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user)
            ->get(route('accounts.export.pdf', $cash))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_guest_cannot_export_ledger(): void
    {
        $cash = Account::query()->where('code', '1100')->firstOrFail();

        $this->get(route('accounts.export.excel', $cash))->assertRedirect();
        $this->get(route('accounts.export.pdf', $cash))->assertRedirect();
    }

    private function accountingUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::AccountingView->value,
            Permission::AccountingManage->value,
        ]);

        return $user;
    }
}
