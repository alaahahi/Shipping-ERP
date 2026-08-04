<?php

namespace App\Notifications;

use App\Enums\VoyageStatus;
use App\Models\Voyage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VoyageStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Voyage $voyage,
        public readonly VoyageStatus $from,
        public readonly VoyageStatus $to
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
        return [
            'type' => 'voyage_status',
            'title' => 'Voyage status updated',
            'body' => sprintf(
                '%s moved from %s to %s.',
                $this->voyage->voyage_number,
                $this->from->label(),
                $this->to->label()
            ),
            'url' => route('voyages.show', $this->voyage),
            'voyage_id' => $this->voyage->id,
            'voyage_number' => $this->voyage->voyage_number,
            'status' => $this->to->value,
        ];
    }
}
