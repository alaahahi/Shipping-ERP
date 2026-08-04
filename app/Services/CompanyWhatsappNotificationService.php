<?php

namespace App\Services;

use App\Enums\WhatsappNotificationType;
use App\Jobs\SendWhatsappNotification;
use App\Models\MoneyVoucher;
use App\Models\Voyage;
use App\Models\VoyageCompany;
use App\Models\WhatsappNotification;
use Illuminate\Support\Facades\Log;

class CompanyWhatsappNotificationService
{
    public function __construct(private readonly WhatsappNotificationService $service)
    {
    }

    public function notifyVoyageCarLoaded(VoyageCompany $voyageCompany, Voyage $voyage): void
    {
        $company = $voyageCompany->company;

        if (! $company || ! $company->notify_whatsapp) {
            return;
        }

        $notification = $this->service->queue(
            $company,
            WhatsappNotificationType::VoyageLoaded,
            [
                'company_name' => $company->name,
                'voyage_number' => $voyage->voyage_number,
                'ship_name' => $voyage->ship?->name ?? '',
            ],
            Voyage::class,
            $voyage->id
        );

        $this->dispatch($notification);
    }

    public function notifyVoyageRevenuePosted(VoyageCompany $voyageCompany, Voyage $voyage, float $amount): void
    {
        $company = $voyageCompany->company;

        if (! $company || ! $company->notify_whatsapp) {
            return;
        }

        $notification = $this->service->queue(
            $company,
            WhatsappNotificationType::VoyageRevenuePosted,
            [
                'company_name' => $company->name,
                'voyage_number' => $voyage->voyage_number,
                'amount' => number_format($amount, 2),
                'currency' => 'USD',
            ],
            Voyage::class,
            $voyage->id
        );

        $this->dispatch($notification);
    }

    public function notifyPaymentReceived(MoneyVoucher $voucher): void
    {
        $company = $voucher->company;

        if (! $company || ! $company->notify_whatsapp) {
            return;
        }

        $amount = $voucher->amount ?? 0;
        $currency = $voucher->currency?->value ?? 'USD';

        $notification = $this->service->queue(
            $company,
            WhatsappNotificationType::PaymentReceived,
            [
                'company_name' => $company->name,
                'amount' => number_format((float) $amount, 2),
                'currency' => $currency,
            ],
            MoneyVoucher::class,
            $voucher->id
        );

        $this->dispatch($notification);
    }

    public function notifyVoyageDeparted(Voyage $voyage): void
    {
        $this->notifyVoyageStatus($voyage, WhatsappNotificationType::VoyageDeparted, [
            'pol' => $voyage->pol ?? '',
        ]);
    }

    public function notifyVoyageArrived(Voyage $voyage): void
    {
        $this->notifyVoyageStatus($voyage, WhatsappNotificationType::VoyageArrived, [
            'pod' => $voyage->pod ?? '',
        ]);
    }

    private function notifyVoyageStatus(Voyage $voyage, WhatsappNotificationType $type, array $extra): void
    {
        foreach ($voyage->companies as $voyageCompany) {
            $company = $voyageCompany->company;

            if (! $company || ! $company->notify_whatsapp) {
                continue;
            }

            $placeholders = array_merge([
                'company_name' => $company->name,
                'voyage_number' => $voyage->voyage_number,
                'ship_name' => $voyage->ship?->name ?? '',
            ], $extra);

            $notification = $this->service->queue(
                $company,
                $type,
                $placeholders,
                Voyage::class,
                $voyage->id
            );

            $this->dispatch($notification);
        }
    }

    private function dispatch(?WhatsappNotification $notification): void
    {
        if (! $notification) {
            return;
        }

        try {
            SendWhatsappNotification::dispatch($notification)->onQueue('whatsapp');
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch WhatsApp notification.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
