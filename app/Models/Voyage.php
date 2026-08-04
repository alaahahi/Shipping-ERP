<?php

namespace App\Models;

use App\Enums\VoyageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voyage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ship_id',
        'voyage_number',
        'sailing_date',
        'arrival_date',
        'pol',
        'pod',
        'captain',
        'status',
        'cost_per_car_aed',
        'captain_commission_aed',
        'purchase_price_aed',
        'notes',
        'revenue_journal_entry_id',
        'commission_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => VoyageStatus::class,
            'sailing_date' => 'date',
            'arrival_date' => 'date',
            'cost_per_car_aed' => 'decimal:2',
            'captain_commission_aed' => 'decimal:2',
            'purchase_price_aed' => 'decimal:2',
        ];
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(VoyageCompany::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(VoyageCar::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(VoyageExpense::class);
    }

    public function waypoints(): HasMany
    {
        return $this->hasMany(VoyageWaypoint::class)->orderBy('sort_order');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(VoyageRoute::class);
    }

    public function revenueJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'revenue_journal_entry_id');
    }

    public function commissionJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'commission_journal_entry_id');
    }

    public function isEditable(): bool
    {
        return $this->status !== VoyageStatus::Closed;
    }
}
