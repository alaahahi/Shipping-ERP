<?php

namespace App\Http\Controllers;

use App\Http\Requests\Accounts\StoreAccountNoteRequest;
use App\Http\Requests\Accounts\UpdateAccountNoteRequest;
use App\Models\Account;
use App\Models\AccountNote;
use App\Models\User;
use App\Services\AccountNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountNoteController extends Controller
{
    public function __construct(
        private readonly AccountNoteService $accountNoteService
    ) {}

    public function store(StoreAccountNoteRequest $request, Account $account): RedirectResponse
    {
        Gate::authorize('create', [AccountNote::class, $account]);

        $this->accountNoteService->create(
            $account,
            $request->user(),
            $request->validated('body')
        );

        return redirect()
            ->route('accounts.show', ['account' => $account, 'tab' => 'notes'])
            ->with('success', 'Note added.');
    }

    public function update(UpdateAccountNoteRequest $request, Account $account, AccountNote $note): RedirectResponse
    {
        $this->assertNoteOnAccount($account, $note);
        Gate::authorize('update', $note);

        $this->accountNoteService->update(
            $note,
            $request->user(),
            $request->validated('body')
        );

        return redirect()
            ->route('accounts.show', ['account' => $account, 'tab' => 'notes'])
            ->with('success', 'Note updated.');
    }

    public function destroy(Request $request, Account $account, AccountNote $note): RedirectResponse
    {
        $this->assertNoteOnAccount($account, $note);
        Gate::authorize('delete', $note);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $this->accountNoteService->delete($note, $user);

        return redirect()
            ->route('accounts.show', ['account' => $account, 'tab' => 'notes'])
            ->with('success', 'Note deleted.');
    }

    private function assertNoteOnAccount(Account $account, AccountNote $note): void
    {
        abort_unless($note->account_id === $account->id, 404);
    }
}
