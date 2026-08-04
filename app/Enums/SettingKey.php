<?php

namespace App\Enums;

enum SettingKey: string
{
    case CompanyName = 'company.name';
    case CompanyEmail = 'company.email';
    case CompanyPhone = 'company.phone';
    case CompanyAddress = 'company.address';

    case AppTimezone = 'app.timezone';
    case AppLocale = 'app.locale';
    case AppCurrency = 'app.currency';

    case WhatsappTenantId = 'whatsapp.tenant_id';
    case WhatsappEnabled = 'whatsapp.enabled';

    public function label(): string
    {
        return match ($this) {
            self::CompanyName => 'Company name',
            self::CompanyEmail => 'Company email',
            self::CompanyPhone => 'Company phone',
            self::CompanyAddress => 'Company address',
            self::AppTimezone => 'Timezone',
            self::AppLocale => 'Locale',
            self::AppCurrency => 'Default currency',
            self::WhatsappTenantId => 'WhatsApp tenant ID',
            self::WhatsappEnabled => 'WhatsApp notifications enabled',
        };
    }

    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    public function defaultValue(): string
    {
        return match ($this) {
            self::CompanyName => 'Shipping ERP',
            self::CompanyEmail => '',
            self::CompanyPhone => '',
            self::CompanyAddress => '',
            self::AppTimezone => 'Asia/Baghdad',
            self::AppLocale => 'ar',
            self::AppCurrency => 'USD',
            self::WhatsappTenantId => 'kaml-kamal',
            self::WhatsappEnabled => '1',
        };
    }
}
