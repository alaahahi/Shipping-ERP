<?php

namespace App\Models;

use App\Enums\WhatsappNotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotification extends Model
{
    protected $fillable = [
        'company_id',
        'tenant_id',
        'phone',
        'type',
        'body',
        'reference_type',
        'reference_id',
        'status',
        'response',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function typeLabel(): string
    {
        $enum = WhatsappNotificationType::tryFrom($this->type);

        return $enum?->label() ?? $this->type;
    }
}
