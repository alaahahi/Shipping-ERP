<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandTripCarPriceChangeItem extends Model
{
    protected $fillable = [
        'land_trip_car_price_change_id',
        'land_trip_car_id',
        'chassis_no',
        'old_price',
        'new_price',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
        ];
    }

    public function change(): BelongsTo
    {
        return $this->belongsTo(LandTripCarPriceChange::class, 'land_trip_car_price_change_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(LandTripCar::class, 'land_trip_car_id');
    }
}
