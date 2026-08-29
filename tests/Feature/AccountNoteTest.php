<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\AccountNote;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_account_page_includes_notes_tab(): void
    {
        $user = $this->accountingUser();
        $account = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)
            ->get(route('accounts.show', ['account' => $account, 'tab' => 'notes']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accounts/Show')
                ->where('tab', 'notes')
                ->has('notes.data', 0));
    }

    public function test_manager_can_add_edit_and_delete_account_note(): void
    {
        $user = $this->accountingUser();
        $account = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.notes.store', $account), [
                'body' => 'Call the owner on Sunday.',
            ])
            ->assertRedirect(route('accounts.show', ['account' => $account, 'tab' => 'notes']));

        $note = AccountNote::query()->where('account_id', $account->id)->firstOrFail();
        $this->assertSame('Call the owner on Sunday.', $note->body);
        $this->assertSame($user->id, $note->created_by);
        $this->assertSame(now()->toDateString(), $note->note_date?->toDateString());

        $this->actingAs($user)
            ->put(route('accounts.notes.update', [$account, $note]), [
                'body' => 'Called. Will pay next week.',
            ])
            ->assertRedirect(route('accounts.show', ['account' => $account, 'tab' => 'notes']));

        $this->assertSame('Called. Will pay next week.', $note->fresh()->body);
        $this->assertSame($user->id, $note->fresh()->updated_by);

        Log::spy();

        $this->actingAs($user)
            ->delete(route('accounts.notes.destroy', [$account, $note]))
            ->assertRedirect(route('accounts.show', ['account' => $account, 'tab' => 'notes']));

        $this->assertSoftDeleted('account_notes', ['id' => $note->id]);
        Log::shouldHaveReceived('info')->withArgs(fn (string $message) => $message === 'Account note deleted.');
    }

    public function test_viewer_cannot_add_account_note(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::AccountingView->value);
        $account = Account::query()->where('code', '1100')->firstOrFail();

        $this->actingAs($user)
            ->post(route('accounts.notes.store', $account), [
                'body' => 'Should not save.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('account_notes', 0);
    }

    public function test_note_cannot_be_updated_on_another_account(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();
        $note = AccountNote::query()->create([
            'account_id' => $cash->id,
            'body' => 'Cash only',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->put(route('accounts.notes.update', [$bank, $note]), [
                'body' => 'Moved',
            ])
            ->assertNotFound();

        $this->assertSame('Cash only', $note->fresh()->body);
    }

    public function test_note_date_can_be_set_and_appears_in_ledger_by_date(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
            'type' => 'receipt',
            'counterpart_account_id' => $bank->id,
            'amount' => 100,
            'entry_date' => '2026-08-10',
            'description' => 'First receipt',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
            'type' => 'receipt',
            'counterpart_account_id' => $bank->id,
            'amount' => 50,
            'entry_date' => '2026-08-20',
            'description' => 'Second receipt',
        ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('accounts.notes.store', $cash), [
                'body' => 'Follow up with the owner.',
                'note_date' => '2026-08-15',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('accounts.show', $cash))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.data.0.description', 'First receipt')
                ->where('lines.data.0.row_type', 'movement')
                ->where('lines.data.1.row_type', 'note')
                ->where('lines.data.1.description', 'Follow up with the owner.')
                ->where('lines.data.1.entry_date', '2026-08-15')
                ->where('lines.data.1.balance', '100.00')
                ->where('lines.data.2.description', 'Second receipt')
                ->where('lines.data.2.balance', '150.00'));
    }

    public function test_ledger_pdf_includes_dated_note(): void
    {
        $user = $this->accountingUser();
        $cash = Account::query()->where('code', '1100')->firstOrFail();
        $bank = Account::query()->where('code', '1200')->firstOrFail();

        $this->actingAs($user)->post(route('accounts.movements.store', $cash), [
            'type' => 'receipt',
            'counterpart_account_id' => $bank->id,
            'amount' => 25,
            'entry_date' => '2026-08-10',
            'description' => 'Exportable receipt',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('accounts.notes.store', $cash), [
            'body' => 'Owner promised to pay Friday.',
            'note_date' => '2026-08-12',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('accounts.export.pdf', $cash))
            ->assertOk()
            ->assertSee('Owner promised to pay Friday.', false);

        $this->actingAs($user)
            ->get(route('accounts.export.excel', $cash))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
