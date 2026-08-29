<?php

namespace App\Models;

use App\Support\ApplicationTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'body',
        'note_date',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (AccountNote $note): void {
            if (blank($note->note_date)) {
                $note->note_date = now(ApplicationTimezone::resolve())->toDateString();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
