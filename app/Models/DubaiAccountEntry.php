<?php

namespace App\Models;

use App\Enums\DubaiEntryKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DubaiAccountEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'dubai_partner_id',
        'entry_date',
        'doc_no',
        'entry_kind',
        'currency',
        'transport_qty',
        'transport_rate',
        'transport_total',
        'forklift_qty',
        'forklift_rate',
        'forklift_total',
        'total_debit',
        'debit',
        'credit',
        'usd_amount',
        'notes',
        'ship_id',
        'voyage_id',
        'excel_file_path',
        'excel_original_name',
        'excel_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'entry_kind' => DubaiEntryKind::class,
            'transport_qty' => 'decimal:2',
            'transport_rate' => 'decimal:4',
            'transport_total' => 'decimal:2',
            'forklift_qty' => 'decimal:2',
            'forklift_rate' => 'decimal:4',
            'forklift_total' => 'decimal:2',
            'total_debit' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'usd_amount' => 'decimal:2',
            'excel_uploaded_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(DubaiPartner::class, 'dubai_partner_id');
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(DubaiCar::class);
    }
}
