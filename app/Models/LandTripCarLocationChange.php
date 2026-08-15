<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandTripCarLocationChange extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'to_location_status_id',
        'cars_count',
        'undone_at',
        'undone_by',
    ];

    protected function casts(): array
    {
        return [
            'cars_count' => 'integer',
            'undone_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function undoneByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }

    public function toLocationStatus(): BelongsTo
    {
        return $this->belongsTo(LandTripCarStatus::class, 'to_location_status_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LandTripCarLocationChangeItem::class);
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }
}
