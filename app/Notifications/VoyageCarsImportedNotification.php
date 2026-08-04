<?php

namespace App\Notifications;

use App\Models\Voyage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VoyageCarsImportedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{imported?: int, skipped?: int, duplicates?: int}|array<string, mixed>  $result
     */
    public function __construct(
        public readonly Voyage $voyage,
        public readonly string $companyName,
        public readonly array $result
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $imported = (int) ($this->result['imported'] ?? $this->result['created'] ?? 0);

        return [
            'type' => 'voyage_import',
            'title' => 'Excel import finished',
            'body' => sprintf(
                '%s · %s — %d car(s) imported.',
                $this->voyage->voyage_number,
                $this->companyName,
                $imported
            ),
            'url' => route('voyages.show', ['voyage' => $this->voyage, 'tab' => 'cars']),
            'voyage_id' => $this->voyage->id,
            'voyage_number' => $this->voyage->voyage_number,
            'result' => $this->result,
        ];
    }
}
