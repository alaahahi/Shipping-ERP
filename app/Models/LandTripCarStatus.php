<?php

namespace App\Models;

use App\Enums\LandTripCarRowTone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandTripCarStatus extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'name_ckb',
        'row_tone',
        'color',
        'match_aliases',
        'sort_order',
        'is_active',
        'is_archive',
        'country_id',
    ];

    protected function casts(): array
    {
        return [
            'row_tone' => LandTripCarRowTone::class,
            'match_aliases' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_archive' => 'boolean',
        ];
    }

    public function resolvedColor(): string
    {
        $color = strtoupper(trim((string) ($this->color ?? '')));
        if (preg_match('/^#[0-9A-F]{6}$/', $color) === 1) {
            return $color;
        }

        return match ($this->row_tone) {
            LandTripCarRowTone::Yellow => '#F59E0B',
            LandTripCarRowTone::Green => '#16A34A',
            default => '#64748B',
        };
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(LandTripCar::class, 'location_status_id');
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $ckb = filled($this->name_ckb) ? $this->name_ckb : null;
        $ar = filled($this->name_ar) ? $this->name_ar : null;
        $en = filled($this->name) ? $this->name : null;

        return match ($locale) {
            'ckb' => $ckb ?? $ar ?? $en ?? '',
            'ar' => $ar ?? $ckb ?? $en ?? '',
            default => $en ?? $ckb ?? $ar ?? '',
        };
    }
}
