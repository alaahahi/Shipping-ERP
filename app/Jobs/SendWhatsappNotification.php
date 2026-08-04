<?php

namespace App\Jobs;

use App\Models\WhatsappNotification;
use App\Services\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsappNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly WhatsappNotification $notification)
    {
    }

    public function handle(WhatsappNotificationService $service): void
    {
        $service->send($this->notification);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
