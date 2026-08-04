<?php

namespace App\Enums;

enum AppLocale: string
{
    case English = 'en';
    case Arabic = 'ar';
    case KurdishSorani = 'ckb';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Arabic => 'العربية',
            self::KurdishSorani => 'کوردی سۆرانی',
        };
    }

    public function isRtl(): bool
    {
        return match ($this) {
            self::Arabic, self::KurdishSorani => true,
            self::English => false,
        };
    }

    /**
     * Locales available in the UI now.
     * Kurdish Sorani is enabled with fallback until full translation is complete.
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return [
            self::Arabic,
            self::English,
            self::KurdishSorani,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $locale): string => $locale->value,
            self::available()
        );
    }
}
