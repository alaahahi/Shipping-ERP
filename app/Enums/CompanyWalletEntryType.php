<?php

namespace App\Enums;

enum CompanyWalletEntryType: string
{
    case Deposit = 'deposit';
    case Withdraw = 'withdraw';

    public function signedMultiplier(): int
    {
        return match ($this) {
            self::Deposit => 1,
            self::Withdraw => -1,
        };
    }
}
