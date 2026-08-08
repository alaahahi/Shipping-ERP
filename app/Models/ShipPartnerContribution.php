<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShipPartnerContribution extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ship_id',
        'owner_id',
        'contribution_date',
        'amount',
        'currency',
        'description',
        'reference',
        'created_by',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'contribution_date' => 'date',
            'amount' => 'decimal:2',
            'currency' => Currency::class,
        ];
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
}
