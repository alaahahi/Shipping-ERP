<?php

namespace App\Support;

use App\Enums\SettingKey;
use App\Services\SettingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

final class ApplicationTimezone
{
    public const DEFAULT = 'Asia/Baghdad';

    public static function apply(?string $timezone = null): string
    {
        $resolved = self::resolve($timezone);

        config(['app.timezone' => $resolved]);
        date_default_timezone_set($resolved);

        return $resolved;
    }

    public static function resolve(?string $timezone = null): string
    {
        $candidate = $timezone ?: self::fromSettings() ?: (string) config('app.timezone', self::DEFAULT);

        return self::isValid($candidate) ? $candidate : self::DEFAULT;
    }

    public static function formatDateTime(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $timezone = self::resolve();

        return Carbon::parse($value)->timezone($timezone)->format('Y-m-d H:i');
    }

    public static function formatNow(): string
    {
        return now(self::resolve())->format('Y-m-d H:i');
    }

    public static function formatNowLabel(): string
    {
        return self::formatNow().' '.self::resolve();
    }

    private static function fromSettings(): ?string
    {
        try {
            $value = app(SettingService::class)->get(SettingKey::AppTimezone);

            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function isValid(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }
}
