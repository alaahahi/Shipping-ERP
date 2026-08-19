<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandTripCarCompanyTransfer extends Model
{
    protected $fillable = [
        'from_company_id',
        'to_company_id',
        'user_id',
        'cars_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cars_count' => 'integer',
        ];
    }

    public function fromCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function toCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LandTripCarCompanyTransferItem::class);
    }
}
