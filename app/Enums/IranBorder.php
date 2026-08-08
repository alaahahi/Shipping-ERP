<?php

namespace App\Enums;

enum IranBorder: string
{
    case AmirAbad = 'amir_abad';
    case Jolfa = 'jolfa';
    case Bazargan = 'bazargan';

    public function label(): string
    {
        return match ($this) {
            self::AmirAbad => 'AMIR ABAD',
            self::Jolfa => 'JOLFA',
            self::Bazargan => 'BAZARGAN',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromHeader(?string $value): ?self
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = (string) preg_replace('/[\s\-_]+/', ' ', $normalized);
        $normalized = trim($normalized);

        return match ($normalized) {
            'AMIR ABAD', 'AMIRABAD' => self::AmirAbad,
            'JOLFA' => self::Jolfa,
            'BAZARGAN', 'BAZRGAN', 'BAZERGAN' => self::Bazargan,
            default => null,
        };
    }
}
