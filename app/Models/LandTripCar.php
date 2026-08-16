<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandTripCar extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'land_trip_id',
        'location_status_id',
        'voyage_car_id',
        'car_id',
        'chassis_no',
        'cmr_waybill',
        'consignee_name',
        'model',
        'color',
        'year',
        'description',
        'weight',
        'price',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'weight' => 'decimal:3',
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function locationStatus(): BelongsTo
    {
        return $this->belongsTo(LandTripCarStatus::class, 'location_status_id');
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
