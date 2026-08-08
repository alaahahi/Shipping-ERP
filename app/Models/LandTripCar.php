<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandTripCar extends Model
{
    protected $fillable = [
        'land_trip_id',
        'voyage_car_id',
        'car_id',
        'chassis_no',
        'consignee_name',
        'description',
        'weight',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
        ];
    }

    public function landTrip(): BelongsTo
    {
        return $this->belongsTo(LandTrip::class);
    }

    public function voyageCar(): BelongsTo
    {
        return $this->belongsTo(VoyageCar::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
