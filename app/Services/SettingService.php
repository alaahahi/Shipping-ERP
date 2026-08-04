<?php

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingService
{
    private const CACHE_KEY = 'app.settings';

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $stored = Setting::query()->pluck('value', 'key')->all();
            $settings = [];

            foreach (SettingKey::cases() as $key) {
                $settings[$key->value] = $stored[$key->value] ?? $key->defaultValue();
            }

            return $settings;
        });
    }

    public function get(SettingKey|string $key, ?string $default = null): string
    {
        $name = $key instanceof SettingKey ? $key->value : $key;
        $settings = $this->all();

        if (array_key_exists($name, $settings)) {
            return (string) $settings[$name];
        }

        if ($default !== null) {
            return $default;
        }

        $enum = SettingKey::tryFrom($name);

        return $enum?->defaultValue() ?? '';
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function updateMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (SettingKey::cases() as $key) {
                if (! array_key_exists($key->value, $values)) {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key->value],
                    [
                        'value' => (string) ($values[$key->value] ?? ''),
                        'group' => $key->group(),
                    ]
                );
            }
        });

        $this->forgetCache();
    }

    public function seedDefaults(): void
    {
        DB::transaction(function (): void {
            foreach (SettingKey::cases() as $key) {
                Setting::query()->firstOrCreate(
                    ['key' => $key->value],
                    [
                        'value' => $key->defaultValue(),
                        'group' => $key->group(),
                    ]
                );
            }
        });

        $this->forgetCache();
    }

    /**
     * @return array{
     *     company: array{name: string, email: string, phone: string, address: string},
     *     app: array{timezone: string, locale: string, currency: string},
     *     whatsapp: array{tenant_id: string, enabled: string}
     * }
     */
    public function formValues(): array
    {
        return [
            'company' => [
                'name' => $this->get(SettingKey::CompanyName),
                'email' => $this->get(SettingKey::CompanyEmail),
                'phone' => $this->get(SettingKey::CompanyPhone),
                'address' => $this->get(SettingKey::CompanyAddress),
            ],
            'app' => [
                'timezone' => $this->get(SettingKey::AppTimezone),
                'locale' => $this->get(SettingKey::AppLocale),
                'currency' => $this->get(SettingKey::AppCurrency),
            ],
            'whatsapp' => [
                'tenant_id' => $this->get(SettingKey::WhatsappTenantId),
                'enabled' => $this->get(SettingKey::WhatsappEnabled),
            ],
        ];
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
