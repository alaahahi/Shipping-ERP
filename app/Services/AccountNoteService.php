<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountNote;
use App\Models\User;
use App\Support\ApplicationTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AccountNoteService
{
    public const PER_PAGE = 20;

    public function paginate(Account $account, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $account->notes()
            ->with(['creator:id,name', 'editor:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'notes_page')
            ->withQueryString();
    }

    public function create(Account $account, User $actor, string $body): AccountNote
    {
        return $account->notes()->create([
            'body' => $body,
            'created_by' => $actor->id,
        ]);
    }

    public function update(AccountNote $note, User $actor, string $body): AccountNote
    {
        $note->update([
            'body' => $body,
            'updated_by' => $actor->id,
        ]);

        $note->load(['creator:id,name', 'editor:id,name']);

        return $note;
    }

    public function delete(AccountNote $note, User $actor): void
    {
        Log::info('Account note deleted.', [
            'account_note_id' => $note->id,
            'account_id' => $note->account_id,
            'deleted_by' => $actor->id,
        ]);

        $note->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(AccountNote $note): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'created_by_name' => $note->creator?->name,
            'updated_by_name' => $note->editor?->name,
            'created_at_label' => ApplicationTimezone::formatDateTime($note->created_at),
            'updated_at_label' => $note->updated_by
                ? ApplicationTimezone::formatDateTime($note->updated_at)
                : null,
        ];
    }
}
