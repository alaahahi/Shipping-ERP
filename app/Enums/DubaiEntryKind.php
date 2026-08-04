<?php

namespace App\Enums;

enum DubaiEntryKind: string
{
    case Shipment = 'shipment';
    case Transfer = 'transfer';
    case Misc = 'misc';

    public function label(): string
    {
        return match ($this) {
            self::Shipment => 'Shipment',
            self::Transfer => 'Transfer',
            self::Misc => 'Miscellaneous',
        };
    }
}
