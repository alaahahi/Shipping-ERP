<?php

namespace App\Models;

use App\Enums\CompanyDirectChargeStatus;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDirectCharge extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voucher_number',
        'company_id',
        'charge_date',
        'currency',
        'amount',
        'credit_account_id',
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
            'status' => CompanyDirectChargeStatus::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'charge_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
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

    public function isPosted(): bool
    {
        return $this->status === CompanyDirectChargeStatus::Posted
            && $this->journal_entry_id
            && $this->journalEntry
            && ! $this->journalEntry->isVoid();
    }
}
