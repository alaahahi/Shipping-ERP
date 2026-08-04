<?php

namespace App\Enums;

enum VoyageExpenseType: string
{
    case Shipping = 'shipping';
    case Fuel = 'fuel';
    case Port = 'port';
    case Customs = 'customs';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Shipping => 'Shipping',
            self::Fuel => 'Fuel',
            self::Port => 'Port',
            self::Customs => 'Customs',
            self::Other => 'Other',
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
