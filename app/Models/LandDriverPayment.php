<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\LandDriverPaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandDriverPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'driver_name',
        'cmr_number',
        'cars_count',
        'type',
        'payment_date',
        'amount',
        'currency',
        'cash_account_id',
        'journal_entry_id',
        'created_by',
        'attachment_path',
        'attachment_original_name',
    ];

    protected function casts(): array
    {
        return [
            'type' => LandDriverPaymentType::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'cars_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedChassis(): MorphMany
    {
        return $this->morphMany(LandPaymentChassis::class, 'payable');
    }

    public function attachmentUrl(): ?string
    {
        if (! $this->attachment_path || ! $this->id || ! $this->company_id) {
            return null;
        }

        $base = rtrim((string) request()->getBasePath(), '/');

        return ($base === '' ? '' : $base).'/land-trips/companies/'.$this->company_id.'/driver-payments/'.$this->id.'/attachment';
    }
}
