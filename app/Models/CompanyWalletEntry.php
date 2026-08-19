<?php

namespace App\Models;

use App\Enums\CompanyWalletEntryType;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'attachment_path',
        'attachment_original_name',
        'journal_entry_id',
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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

        return ($base === '' ? '' : $base).'/land-trips/companies/'.$this->company_id.'/wallet/'.$this->id.'/attachment';
    }
}
