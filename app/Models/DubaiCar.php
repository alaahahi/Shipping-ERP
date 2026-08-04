<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DubaiCar extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'dubai_account_entry_id',
        'row_number',
        'chassis_no',
        'consignee_name',
        'shipper_name',
        'description',
        'weight',
        'code',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DubaiAccountEntry::class, 'dubai_account_entry_id');
    }
}
