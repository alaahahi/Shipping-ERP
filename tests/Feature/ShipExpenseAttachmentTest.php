<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Enums\ShipExpenseType;
use App\Models\Attachment;
use App\Models\Ship;
use App\Models\ShipExpense;
use App\Models\User;
use App\Services\ShipService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShipExpenseAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manager_can_store_expense_with_attachment_and_print_voucher(): void
    {
        Storage::fake('public');

        $user = $this->shipsManager();
        $ship = $this->makeShip();
        $file = UploadedFile::fake()->create('fuel-receipt.pdf', 40, 'application/pdf');

        $this->actingAs($user)
            ->post(route('ships.expenses.store', $ship), [
                'expense_type' => ShipExpenseType::Fuel->value,
                'amount' => 250,
                'currency' => Currency::USD->value,
                'expense_date' => '2026-08-18',
                'vendor' => 'Port fuel supplier',
                'attachment' => $file,
            ])
            ->assertRedirect(route('ships.show', ['ship' => $ship, 'tab' => 'expenses']));

        $expense = ShipExpense::query()->firstOrFail();
        $attachment = $expense->latestAttachment;

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->assertSame('ship_expense', $attachment->attachable_type);
        $this->assertSame($expense->id, $attachment->attachable_id);
        $this->assertSame('fuel-receipt.pdf', $attachment->original_name);
        Storage::disk('public')->assertExists($attachment->path);

        $this->actingAs($user)
            ->get(route('ships.expenses.attachment', [$ship, $expense]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('ships.expenses.voucher', [$ship, $expense]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ships/ExpenseVoucherPrint')
                ->where('expense.party_name', 'Port fuel supplier')
                ->where('expense.currency', 'USD')
                ->where('expense.amount', '250.00')
            );
    }

    public function test_viewer_can_print_and_view_attachment_but_cannot_create(): void
    {
        Storage::fake('public');

        $manager = $this->shipsManager();
        $viewer = $this->shipsViewer();
        $ship = $this->makeShip();
        $file = UploadedFile::fake()->image('invoice.jpg');

        $this->actingAs($manager)
            ->post(route('ships.expenses.store', $ship), [
                'expense_type' => ShipExpenseType::Other->value,
                'amount' => 40,
                'currency' => Currency::USD->value,
                'expense_date' => '2026-08-18',
                'vendor' => 'Chandlery',
                'attachment' => $file,
            ])
            ->assertRedirect();

        $expense = ShipExpense::query()->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('ships.expenses.voucher', [$ship, $expense]))
            ->assertOk();

        $this->actingAs($viewer)
            ->get(route('ships.expenses.attachment', [$ship, $expense]))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('ships.expenses.store', $ship), [
                'expense_type' => ShipExpenseType::Food->value,
                'amount' => 10,
                'currency' => Currency::USD->value,
                'expense_date' => '2026-08-18',
            ])
            ->assertForbidden();
    }

    public function test_deleting_expense_soft_deletes_attachments(): void
    {
        Storage::fake('public');

        $user = $this->shipsManager();
        $ship = $this->makeShip();

        $this->actingAs($user)
            ->post(route('ships.expenses.store', $ship), [
                'expense_type' => ShipExpenseType::Crew->value,
                'amount' => 80,
                'currency' => Currency::USD->value,
                'expense_date' => '2026-08-18',
                'attachment' => UploadedFile::fake()->create('crew.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $expense = ShipExpense::query()->firstOrFail();
        $attachment = $expense->latestAttachment;
        $this->assertNotNull($attachment);

        $this->actingAs($user)
            ->delete(route('ships.expenses.destroy', [$ship, $expense]))
            ->assertRedirect();

        $this->assertSoftDeleted($expense);
        $this->assertSoftDeleted($attachment);
    }

    public function test_manager_can_replace_expense_attachment(): void
    {
        Storage::fake('public');

        $user = $this->shipsManager();
        $ship = $this->makeShip();

        $this->actingAs($user)
            ->post(route('ships.expenses.store', $ship), [
                'expense_type' => ShipExpenseType::Fuel->value,
                'amount' => 50,
                'currency' => Currency::USD->value,
                'expense_date' => '2026-08-18',
                'attachment' => UploadedFile::fake()->image('old.jpg'),
            ])
            ->assertRedirect();

        $expense = ShipExpense::query()->firstOrFail();
        $oldPath = $expense->latestAttachment?->path;

        $this->actingAs($user)
            ->post(route('ships.expenses.attachment.update', [$ship, $expense]), [
                'attachment' => UploadedFile::fake()->create('new.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect(route('ships.show', ['ship' => $ship, 'tab' => 'expenses']));

        $expense->refresh()->load('latestAttachment');
        $this->assertSame('new.pdf', $expense->latestAttachment?->original_name);
        $this->assertNotSame($oldPath, $expense->latestAttachment?->path);
        Storage::disk('public')->assertExists($expense->latestAttachment->path);
        $this->assertEquals(50.0, (float) $expense->amount);
    }

    public function test_viewer_cannot_replace_expense_attachment(): void
    {
        Storage::fake('public');

        $manager = $this->shipsManager();
        $ship = $this->makeShip();

        $this->actingAs($manager)
            ->post(route('ships.expenses.store', $ship), [
                'expense_type' => ShipExpenseType::Other->value,
                'amount' => 12,
                'currency' => Currency::USD->value,
                'expense_date' => '2026-08-18',
                'attachment' => UploadedFile::fake()->image('keep.jpg'),
            ])
            ->assertRedirect();

        $expense = ShipExpense::query()->firstOrFail();
        $viewer = $this->shipsViewer();

        $this->actingAs($viewer)
            ->post(route('ships.expenses.attachment.update', [$ship, $expense]), [
                'attachment' => UploadedFile::fake()->image('hack.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame('keep.jpg', $expense->fresh()->latestAttachment?->original_name);
    }

    public function test_unauthorized_user_cannot_open_voucher_or_attachment(): void
    {
        Storage::fake('public');

        $manager = $this->shipsManager();
        $outsider = User::factory()->create();
        $ship = $this->makeShip();

        $this->actingAs($manager)
            ->post(route('ships.expenses.store', $ship), [
                'expense_type' => ShipExpenseType::Rent->value,
                'amount' => 15,
                'currency' => Currency::USD->value,
                'expense_date' => '2026-08-18',
                'attachment' => UploadedFile::fake()->create('rent.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect();

        $expense = ShipExpense::query()->firstOrFail();

        $this->actingAs($outsider)
            ->get(route('ships.expenses.voucher', [$ship, $expense]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('ships.expenses.attachment', [$ship, $expense]))
            ->assertForbidden();
    }

    private function shipsManager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            Permission::ShipsView->value,
            Permission::ShipsManage->value,
        ]);

        return $user;
    }

    private function shipsViewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::ShipsView->value);

        return $user;
    }

    private function makeShip(): Ship
    {
        return app(ShipService::class)->create([
            'name' => 'MV Test Vessel',
            'is_active' => true,
        ]);
    }
}
