<?php

namespace App\Enums;

enum LandTripCarRowTone: string
{
    case Yellow = 'yellow';
    case Green = 'green';
    case Neutral = 'neutral';

    public function label(): string
    {
        return match ($this) {
            self::Yellow => 'Yellow',
            self::Green => 'Green',
            self::Neutral => 'Neutral',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
