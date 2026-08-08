<?php

namespace App\Enums;

enum LandTripStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'info',
            self::Closed => 'success',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active],
            self::Active => [self::Closed, self::Draft],
            self::Closed => [],
        };
    }
}
