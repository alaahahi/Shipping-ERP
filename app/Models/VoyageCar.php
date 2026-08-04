<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoyageCar extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voyage_id',
        'voyage_company_id',
        'car_id',
        'chassis_no',
        'consignee_name',
        'shipper_name',
        'description',
        'weight',
        'code',
        'row_number',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'row_number' => 'integer',
        ];
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(VoyageCompany::class, 'voyage_company_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
