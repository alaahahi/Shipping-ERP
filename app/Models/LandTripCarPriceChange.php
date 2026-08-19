<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandTripCarPriceChange extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'batch_uuid',
        'cars_count',
        'new_price',
    ];

    protected function casts(): array
    {
        return [
            'cars_count' => 'integer',
            'new_price' => 'decimal:2',
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

    public function items(): HasMany
    {
        return $this->hasMany(LandTripCarPriceChangeItem::class);
    }
}
