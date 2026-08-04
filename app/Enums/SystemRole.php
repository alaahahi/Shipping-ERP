<?php

namespace App\Enums;

enum SystemRole: string
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Accountant => 'Accountant',
            self::Operator => 'Operator',
            self::Viewer => 'Viewer',
        };
    }
}
