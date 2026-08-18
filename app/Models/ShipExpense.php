<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ShipExpenseType;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShipExpense extends Model
{
    use HasAttachments, SoftDeletes;

    protected $fillable = [
        'ship_id',
        'expense_type',
        'amount',
        'currency',
        'expense_date',
        'vendor',
        'reference',
        'notes',
        'created_by',
        'paid_by_owner_id',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'expense_type' => ShipExpenseType::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidByOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'paid_by_owner_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isPostedToAccounting(): bool
    {
        return $this->journal_entry_id !== null
            && $this->journalEntry
            && ! $this->journalEntry->isVoid();
    }

    public function attachmentUrl(): ?string
    {
        if (! $this->id || ! $this->ship_id || ! $this->latestAttachment) {
            return null;
        }

        return route('ships.expenses.attachment', [$this->ship_id, $this->id]);
    }
}
