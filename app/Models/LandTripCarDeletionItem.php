<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandTripCarDeletionItem extends Model
{
    protected $fillable = [
        'land_trip_car_deletion_id',
        'land_trip_car_id',
        'chassis_no',
        'model',
        'cmr_waybill',
        'restored_at',
        'restored_by',
    ];

    protected function casts(): array
    {
        return [
            'restored_at' => 'datetime',
        ];
    }

    public function deletion(): BelongsTo
    {
        return $this->belongsTo(LandTripCarDeletion::class, 'land_trip_car_deletion_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(LandTripCar::class, 'land_trip_car_id')->withTrashed();
    }

    public function restoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function isRestored(): bool
    {
        return $this->restored_at !== null;
    }
}
