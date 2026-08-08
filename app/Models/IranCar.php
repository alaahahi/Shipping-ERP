<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\IranBorder;
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
        'notes',
        'status',
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
            'status' => IranCarStatus::class,
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

    public function remainingAmount(): float
    {
        return round((float) $this->total_amount - $this->paidAmount(), 2);
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

    public function isTotalLocked(): bool
    {
        return $this->hasPayments();
    }
}
