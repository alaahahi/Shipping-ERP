<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandTripCarCompanyTransferItem extends Model
{
    protected $fillable = [
        'land_trip_car_company_transfer_id',
        'land_trip_car_id',
        'chassis_no',
        'from_land_trip_id',
        'to_land_trip_id',
        'cmr_waybill',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(LandTripCarCompanyTransfer::class, 'land_trip_car_company_transfer_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(LandTripCar::class, 'land_trip_car_id');
    }

    public function fromLandTrip(): BelongsTo
    {
        return $this->belongsTo(LandTrip::class, 'from_land_trip_id');
    }

    public function toLandTrip(): BelongsTo
    {
        return $this->belongsTo(LandTrip::class, 'to_land_trip_id');
    }
}
