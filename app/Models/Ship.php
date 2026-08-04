<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ship extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'flag',
        'imo_number',
        'call_sign',
        'default_captain',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function voyages(): HasMany
    {
        return $this->hasMany(Voyage::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(ShipOwnership::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ShipExpense::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'ship_ownerships')
            ->withPivot(['id', 'share_percent', 'is_managing', 'effective_from', 'notes'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }
}
