<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IranCarPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'iran_car_id',
        'voucher_number',
        'payment_date',
        'amount',
        'currency',
        'debit_account_id',
        'journal_entry_id',
        'reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'currency' => Currency::class,
        ];
    }

    public function iranCar(): BelongsTo
    {
        return $this->belongsTo(IranCar::class);
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
