<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\MoneyVoucherStatus;
use App\Enums\MoneyVoucherType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoneyVoucher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voucher_number',
        'type',
        'voucher_date',
        'currency',
        'amount',
        'payment_account_id',
        'company_id',
        'voyage_id',
        'counterparty',
        'reference',
        'description',
        'status',
        'journal_entry_id',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MoneyVoucherType::class,
            'status' => MoneyVoucherStatus::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'voucher_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MoneyVoucherAllocation::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isDraft(): bool
    {
        return $this->status === MoneyVoucherStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === MoneyVoucherStatus::Posted
            && $this->journal_entry_id
            && $this->journalEntry
            && ! $this->journalEntry->isVoid();
    }
}
