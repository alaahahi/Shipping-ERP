<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoyageRoute extends Model
{
    protected $fillable = [
        'voyage_id',
        'route_type',
        'coordinates',
        'color',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
        ];
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }
}
