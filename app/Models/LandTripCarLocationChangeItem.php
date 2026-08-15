<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandTripCarLocationChangeItem extends Model
{
    protected $fillable = [
        'land_trip_car_location_change_id',
        'land_trip_car_id',
        'from_location_status_id',
        'to_location_status_id',
        'chassis_no',
    ];

    public function change(): BelongsTo
    {
        return $this->belongsTo(LandTripCarLocationChange::class, 'land_trip_car_location_change_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(LandTripCar::class, 'land_trip_car_id');
    }

    public function fromLocationStatus(): BelongsTo
    {
        return $this->belongsTo(LandTripCarStatus::class, 'from_location_status_id');
    }

    public function toLocationStatus(): BelongsTo
    {
        return $this->belongsTo(LandTripCarStatus::class, 'to_location_status_id');
    }
}
