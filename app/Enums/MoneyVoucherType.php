<?php

namespace App\Enums;

enum MoneyVoucherType: string
{
    case Receipt = 'receipt';
    case Payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Payment => 'Payment',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Receipt => 'RV',
            self::Payment => 'PV',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Receipt => 'success',
            self::Payment => 'warning',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->all();
    }
}
