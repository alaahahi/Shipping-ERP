<?php

namespace App\Enums;

enum IranCarSaleState: string
{
    case Unsold = 'unsold';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Unsold => 'Unsold',
            self::Sold => 'Sold',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unsold => 'warning',
            self::Sold => 'success',
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
