<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\JournalStatus;
use App\Enums\Permission;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AccountManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_manager_can_rename_system_account(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $this->assertTrue((bool) $cash->is_system);

        $this->actingAs($user)
            ->put(route('accounts.update', $cash), [
                'code' => $cash->code,
                'name' => 'Cash Renamed',
                'type' => $cash->type->value,
                'currency' => $cash->currency->value,
                'parent_id' => $cash->parent_id,
                'description' => $cash->description,
                'is_active' => true,
                'show_on_dashboard' => false,
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertSame('Cash Renamed', $cash->fresh()->name);
    }

    public function test_system_account_type_locked_after_posted_movements(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->postMovement($user, $cash, $bank);

        $this->actingAs($user)
            ->from(route('accounts.edit', $cash))
            ->put(route('accounts.update', $cash), [
                'code' => $cash->code,
                'name' => $cash->name,
                'type' => AccountType::Expense->value,
                'currency' => $cash->currency->value,
                'parent_id' => $cash->parent_id,
                'description' => $cash->description,
                'is_active' => true,
                'show_on_dashboard' => false,
            ])
            ->assertRedirect(route('accounts.edit', $cash))
            ->assertSessionHasErrors('type');

        $this->assertSame(AccountType::Asset, $cash->fresh()->type);
    }

    public function test_system_account_can_be_deleted_without_posted_movements(): void
    {
        $user = $this->accountingUser();
        $account = Account::query()->create([
            'code' => '9999',
            'name' => 'Temp System',
            'type' => AccountType::Expense->value,
            'currency' => Currency::USD->value,
            'is_system' => true,
            'is_active' => true,
        ]);

        Log::spy();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));

        $this->assertSoftDeleted($account);
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($account, $user): bool {
            return $message === 'Account soft-deleted'
                && $context['account_id'] === $account->id
                && $context['is_system'] === true
                && $context['deleted_by'] === $user->id;
        });
    }

    public function test_account_with_posted_movements_cannot_be_deleted(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->postMovement($user, $cash, $bank);

        $this->actingAs($user)
            ->from(route('accounts.index'))
            ->delete(route('accounts.destroy', $cash))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHasErrors('account');

        $this->assertNotSoftDeleted($cash);
    }

    private function postMovement(User $user, Account $debit, Account $credit): void
    {
        $entry = JournalEntry::query()->create([
            'voucher_number' => 'TST-'.uniqid(),
            'entry_date' => '2026-08-15',
            'currency' => $debit->currency->value,
            'description' => 'Test movement',
            'status' => JournalStatus::Posted,
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $debit->id,
            'debit' => 10,
            'credit' => 0,
        ]);

        JournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $credit->id,
            'debit' => 0,
            'credit' => 10,
        ]);
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
