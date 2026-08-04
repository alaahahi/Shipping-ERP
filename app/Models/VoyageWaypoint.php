<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoyageWaypoint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voyage_id',
        'name',
        'reached_at',
        'latitude',
        'longitude',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reached_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'sort_order' => 'integer',
        ];
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }
}
