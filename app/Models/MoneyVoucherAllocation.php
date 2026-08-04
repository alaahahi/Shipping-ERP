<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyVoucherAllocation extends Model
{
    protected $fillable = [
        'money_voucher_id',
        'voyage_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function moneyVoucher(): BelongsTo
    {
        return $this->belongsTo(MoneyVoucher::class);
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }
}
