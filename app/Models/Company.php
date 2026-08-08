<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'contact_name',
        'contact_phone',
        'whatsapp_phone',
        'notify_whatsapp',
        'email',
        'address',
        'notes',
        'is_active',
        'ar_account_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notify_whatsapp' => 'boolean',
        ];
    }

    public function whatsappNumber(): ?string
    {
        return $this->whatsapp_phone ?: $this->contact_phone;
    }

    public function arAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ar_account_id');
    }

    public function directCharges(): HasMany
    {
        return $this->hasMany(CompanyDirectCharge::class);
    }

    public function whatsappNotifications(): HasMany
    {
        return $this->hasMany(WhatsappNotification::class);
    }

    public function voyageCompanies(): HasMany
    {
        return $this->hasMany(VoyageCompany::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function moneyVouchers(): HasMany
    {
        return $this->hasMany(MoneyVoucher::class);
    }
}