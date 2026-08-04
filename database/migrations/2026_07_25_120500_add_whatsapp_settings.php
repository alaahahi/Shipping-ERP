<?php

use App\Enums\SettingKey;
use App\Services\SettingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(SettingService::class);
        $service->updateMany([
            SettingKey::WhatsappTenantId->value => 'kaml-kamal',
            SettingKey::WhatsappEnabled->value => '1',
        ]);
    }

    public function down(): void
    {
        $service = app(SettingService::class);
        $service->updateMany([
            SettingKey::WhatsappTenantId->value => '',
            SettingKey::WhatsappEnabled->value => '0',
        ]);
    }
};
