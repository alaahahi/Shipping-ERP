<?php

namespace App\Support;

/**
 * Temporary VIN rule: the letter O is not allowed in chassis numbers.
 * New/import values replace O with 0. Existing rows keep O and show a UI warning
 * until this check is removed.
 */
final class ChassisLetterO
{
    public static function replace(string $chassis): string
    {
        return str_replace(['O', 'o'], '0', $chassis);
    }

    public static function exists(?string $chassis): bool
    {
        if ($chassis === null || $chassis === '') {
            return false;
        }

        return str_contains(strtoupper($chassis), 'O');
    }
}
