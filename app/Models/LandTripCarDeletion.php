<?php

namespace App\Models;

use App\Enums\LandTripCarDeletionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandTripCarDeletion extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'cars_count',
        'source',
        'restored_at',
        'restored_by',
    ];

    protected function casts(): array
    {
        return [
            'cars_count' => 'integer',
            'source' => LandTripCarDeletionSource::class,
            'restored_at' => 'datetime',
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

    public function restoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LandTripCarDeletionItem::class);
    }

    public function isFullyRestored(): bool
    {
        return $this->restored_at !== null;
    }
}
