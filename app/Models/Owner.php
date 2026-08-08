<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Owner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'national_id',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(ShipOwnership::class);
    }

    public function shipContributions(): HasMany
    {
        return $this->hasMany(ShipPartnerContribution::class);
    }

    public function ships(): BelongsToMany
    {
        return $this->belongsToMany(Ship::class, 'ship_ownerships')
            ->withPivot(['id', 'share_percent', 'is_managing', 'effective_from', 'notes'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }
}
