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
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'notes_page')
            ->withQueryString();
    }

    /**
     * @param  array{voucher?: string|null, description?: string|null, amount?: float|int|string|null}  $search
     * @return list<array<string, mixed>>
     */
    public function ledgerRows(Account $account, ?string $dateFrom, ?string $dateTo, array $search = []): array
    {
        $voucher = trim((string) ($search['voucher'] ?? ''));
        $amount = $search['amount'] ?? null;
        if ($voucher !== '' || ($amount !== null && $amount !== '')) {
            return [];
        }

        $description = trim((string) ($search['description'] ?? ''));

        return $account->notes()
            ->when($dateFrom, fn ($query) => $query->whereDate('note_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('note_date', '<=', $dateTo))
            ->when($description !== '', function ($query) use ($description): void {
                $query->where('body', 'like', '%'.$this->escapeLike($description).'%');
            })
            ->orderBy('note_date')
            ->orderBy('id')
            ->get()
            ->map(fn (AccountNote $note): array => [
                'id' => 'note-'.$note->id,
                'row_type' => 'note',
                'note_id' => $note->id,
                'entry_date' => $note->note_date?->format('Y-m-d'),
                'voucher_number' => null,
                'journal_entry_id' => null,
                'description' => $note->body,
                'reference' => null,
                'memo' => null,
                'attachment_url' => null,
                'has_attachment' => false,
                'counterpart' => null,
                'debit' => null,
                'credit' => null,
                'balance' => null,
            ])
            ->all();
    }

    public function create(Account $account, User $actor, string $body, ?string $noteDate = null): AccountNote
    {
        return $account->notes()->create([
            'body' => $body,
            'note_date' => $this->resolveNoteDate($noteDate),
            'created_by' => $actor->id,
        ]);
    }

    public function update(AccountNote $note, User $actor, string $body, ?string $noteDate = null): AccountNote
    {
        $payload = [
            'body' => $body,
            'updated_by' => $actor->id,
        ];

        if ($noteDate !== null && trim($noteDate) !== '') {
            $payload['note_date'] = $this->resolveNoteDate($noteDate);
        }

        $note->update($payload);
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
            'note_date' => $note->note_date?->format('Y-m-d'),
            'created_by_name' => $note->creator?->name,
            'updated_by_name' => $note->editor?->name,
            'created_at_label' => ApplicationTimezone::formatDateTime($note->created_at),
            'updated_at_label' => $note->updated_by
                ? ApplicationTimezone::formatDateTime($note->updated_at)
                : null,
        ];
    }

    public function resolveNoteDate(?string $noteDate): string
    {
        $trimmed = trim((string) $noteDate);

        if ($trimmed === '') {
            return now(ApplicationTimezone::resolve())->toDateString();
        }

        return $trimmed;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
