<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\IranBorder;
use App\Enums\IranCarSaleState;
use App\Enums\IranCarStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IranCar extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'border',
        'vin',
        'model_name',
        'year',
        'color',
        'currency',
        'total_amount',
        'sale_price',
        'notes',
        'status',
        'sale_state',
        'sold_at',
        'sold_by',
        'invoice_journal_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'border' => IranBorder::class,
            'year' => 'integer',
            'currency' => Currency::class,
            'total_amount' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'status' => IranCarStatus::class,
            'sale_state' => IranCarSaleState::class,
            'sold_at' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoiceJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'invoice_journal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(IranCarPayment::class);
    }

    public function paidAmount(): float
    {
        if (array_key_exists('payments_sum_amount', $this->attributes)) {
            return round((float) $this->attributes['payments_sum_amount'], 2);
        }

        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function billedAmount(): float
    {
        if (! $this->isSold()) {
            return 0.0;
        }

        return round((float) ($this->sale_price ?? 0), 2);
    }

    public function remainingAmount(): float
    {
        return round($this->billedAmount() - $this->paidAmount(), 2);
    }

    public function hasPayments(): bool
    {
        if (array_key_exists('payments_count', $this->attributes)) {
            return (int) $this->attributes['payments_count'] > 0;
        }

        return $this->payments()->exists();
    }

    public function isCancelled(): bool
    {
        return $this->status === IranCarStatus::Cancelled;
    }

    public function isSold(): bool
    {
        return $this->sale_state === IranCarSaleState::Sold;
    }

    public function isTotalLocked(): bool
    {
        return $this->isSold() && $this->hasPayments();
    }
}
