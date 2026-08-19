<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandPaymentChassis extends Model
{
    use SoftDeletes;

    protected $table = 'land_payment_chassis';

    protected $fillable = [
        'company_id',
        'payable_type',
        'payable_id',
        'land_trip_car_id',
        'chassis_no',
        'created_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(LandTripCar::class, 'land_trip_car_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
