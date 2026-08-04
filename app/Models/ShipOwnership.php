<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShipOwnership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ship_id',
        'owner_id',
        'share_percent',
        'is_managing',
        'effective_from',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'share_percent' => 'decimal:2',
            'is_managing' => 'boolean',
            'effective_from' => 'date',
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
}
