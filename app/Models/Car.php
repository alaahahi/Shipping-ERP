<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vin',
        'description',
        'color',
        'year',
        'notes',
    ];

    public function voyageCars(): HasMany
    {
        return $this->hasMany(VoyageCar::class);
    }
}
