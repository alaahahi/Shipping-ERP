<?php

namespace App\Enums;

enum Currency: string
{
    case USD = 'USD';
    case AED = 'AED';
    case IQD = 'IQD';
    case EUR = 'EUR';

    public function label(): string
    {
        return match ($this) {
            self::USD => 'USD - US Dollar',
            self::AED => 'AED - UAE Dirham',
            self::IQD => 'IQD - Iraqi Dinar',
            self::EUR => 'EUR - Euro',
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
