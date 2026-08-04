<?php

namespace App\Enums;

enum ShipExpenseType: string
{
    case Maintenance = 'maintenance';
    case Insurance = 'insurance';
    case Drydock = 'drydock';
    case Crew = 'crew';
    case Supplies = 'supplies';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::Insurance => 'Insurance',
            self::Drydock => 'Drydock',
            self::Crew => 'Crew',
            self::Supplies => 'Supplies',
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
