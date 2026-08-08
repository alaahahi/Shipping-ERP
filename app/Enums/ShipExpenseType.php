<?php

namespace App\Enums;

enum ShipExpenseType: string
{
    case Maintenance = 'maintenance';
    case Insurance = 'insurance';
    case Drydock = 'drydock';
    case Crew = 'crew';
    case Salary = 'salary';
    case Fuel = 'fuel';
    case Rent = 'rent';
    case Food = 'food';
    case Transfer = 'transfer';
    case Supplies = 'supplies';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::Insurance => 'Insurance',
            self::Drydock => 'Drydock',
            self::Crew => 'Crew',
            self::Salary => 'Salary',
            self::Fuel => 'Fuel',
            self::Rent => 'Rent',
            self::Food => 'Food',
            self::Transfer => 'Transfer / commission',
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
