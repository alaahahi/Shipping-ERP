<?php

namespace App\Enums;

enum LandDriverPaymentType: string
{
    case Freight = 'freight';
    case Commission = 'commission';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Freight => 'Freight',
            self::Commission => 'Commission',
            self::Other => 'Other',
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
