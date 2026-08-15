<?php

namespace App\Models;

use App\Enums\CompanyWalletEntryType;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyWalletEntry extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'company_id',
        'voucher_number',
        'type',
        'amount',
        'currency',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyWalletEntryType::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
