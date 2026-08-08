<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'iso_code',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (in_array($locale, ['ar', 'ckb'], true) && filled($this->name_ar)) {
            return $this->name_ar;
        }

        return $this->name;
    }

    public function tripsFrom(): HasMany
    {
        return $this->hasMany(LandTrip::class, 'from_country_id');
    }

    public function tripsTo(): HasMany
    {
        return $this->hasMany(LandTrip::class, 'to_country_id');
    }
}
