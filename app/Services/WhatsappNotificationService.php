<?php

namespace App\Services;

use App\Enums\SettingKey;
use App\Enums\WhatsappNotificationType;
use App\Models\Company;
use App\Models\WhatsappNotification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappNotificationService
{
    private const BASE_URL = 'https://wa.intellij-app.com';

    private SettingService $settings;

    public function __construct(SettingService $settings)
    {
        $this->settings = $settings;
    }

    public function isEnabled(): bool
    {
        return $this->settings->get(SettingKey::WhatsappEnabled) === '1';
    }

    public function tenantId(): string
    {
        return $this->settings->get(SettingKey::WhatsappTenantId, 'kaml-kamal');
    }

    public function queue(
        Company $company,
        WhatsappNotificationType $type,
        array $placeholders,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): ?WhatsappNotification {
        if (! $this->isEnabled()) {
            return null;
        }

        $phone = $company->whatsappNumber();

        if (! $this->isValidPhone($phone)) {
            Log::info('WhatsApp notification skipped: company has no phone.', [
                'company_id' => $company->id,
                'type' => $type->value,
            ]);

            return null;
        }

        $body = $this->renderTemplate($type->defaultTemplate(), $placeholders);

        $notification = new WhatsappNotification([
            'company_id' => $company->id,
            'tenant_id' => $this->tenantId(),
            'phone' => $phone,
            'type' => $type->value,
            'body' => $body,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => 'pending',
        ]);

        $notification->save();

        return $notification;
    }

    public function send(WhatsappNotification $notification): bool
    {
        $notification->status = 'queued';
        $notification->queued_at = now();
        $notification->save();

        try {
            $response = $this->apiCall($notification);

            $notification->response = $response->body();

            if ($response->successful()) {
                $notification->status = 'sent';
                $notification->sent_at = now();
                $notification->save();

                return true;
            }

            $notification->status = 'failed';
            $notification->failed_at = now();
            $notification->save();

            Log::warning('WhatsApp API returned non-success status.', [
                'notification_id' => $notification->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $notification->status = 'failed';
            $notification->failed_at = now();
            $notification->response = $e->getMessage();
            $notification->save();

            Log::error('WhatsApp notification failed.', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function apiCall(WhatsappNotification $notification): Response
    {
        return Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post(
                self::BASE_URL . '/kaml-kamal/api/v1/queue',
                [
                    'tenant_id' => $notification->tenant_id,
                    'phone' => $this->normalizePhone($notification->phone),
                    'message' => $notification->body,
                ]
            );
    }

    public function renderTemplate(string $template, array $placeholders): string
    {
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            fn (array $matches) => (string) ($placeholders[$matches[1]] ?? $matches[0]),
            $template
        );
    }

    private function isValidPhone(?string $phone): bool
    {
        if (! $phone) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) >= 7;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (! str_starts_with($digits, '+')) {
            $digits = '+' . $digits;
        }

        return $digits;
    }
}
