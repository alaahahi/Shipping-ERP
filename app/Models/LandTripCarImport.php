<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandTripCarImport extends Model
{
    protected $fillable = [
        'company_id',
        'land_trip_id',
        'user_id',
        'original_filename',
        'imported_count',
        'updated_count',
        'skipped_count',
        'created_car_ids',
        'undone_at',
        'undone_by',
    ];

    protected function casts(): array
    {
        return [
            'imported_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'created_car_ids' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function landTrip(): BelongsTo
    {
        return $this->belongsTo(LandTrip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function undoneByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }

    /**
     * @return list<int>
     */
    public function createdCarIds(): array
    {
        $ids = $this->created_car_ids ?? [];

        return array_values(array_unique(array_map('intval', is_array($ids) ? $ids : [])));
    }
}
