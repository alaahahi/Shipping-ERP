<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoyageCompany extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'voyage_id',
        'company_id',
        'company_name',
        'contact_name',
        'contact_phone',
        'shipping_price_per_car',
        'shipping_price_aed',
        'clearance_per_car',
        'notes',
        'excel_file_path',
        'excel_original_name',
        'excel_uploaded_at',
        'excel_imported_count',
    ];

    protected function casts(): array
    {
        return [
            'shipping_price_per_car' => 'decimal:2',
            'shipping_price_aed' => 'decimal:2',
            'clearance_per_car' => 'decimal:2',
            'excel_uploaded_at' => 'datetime',
            'excel_imported_count' => 'integer',
        ];
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(Voyage::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(VoyageCar::class);
    }

    public function unitTotalUsd(): float
    {
        return (float) $this->shipping_price_per_car + (float) $this->clearance_per_car;
    }
}
