<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\LandTripStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandTrip extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cmr_number',
        'driver_name',
        'from_country_id',
        'to_country_id',
        'departure_date',
        'arrival_date',
        'company_id',
        'freight_amount',
        'currency',
        'status',
        'voyage_id',
        'notes',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'arrival_date' => 'date',
            'freight_amount' => 'decimal:2',
            'currency' => Currency::class,
            'status' => LandTripStatus::class,
        ];
    }

    public function fromCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'from_country_id');
    }

    public function toCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'to_country_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cars(): HasMany
    {
        return $this->hasMany(LandTripCar::class);
    }

    public function isEditable(): bool
    {
        return $this->status !== LandTripStatus::Closed && $this->journal_entry_id === null;
    }

    public function isPosted(): bool
    {
        return $this->journal_entry_id !== null;
    }
}
