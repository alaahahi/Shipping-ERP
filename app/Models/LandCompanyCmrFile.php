<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandCompanyCmrFile extends Model
{
    protected $fillable = [
        'company_id',
        'cmr_key',
        'attachment_path',
        'original_name',
        'uploaded_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function publicUrl(): ?string
    {
        if (! $this->attachment_path || ! $this->id) {
            return null;
        }

        return route('land-trips.companies.cmr-files.show', [
            'company' => $this->company_id,
            'cmrFile' => $this->id,
        ]);
    }
}
