<?php

namespace App\Support;

use NumberFormatter;

class AmountInWords
{
    public static function arabic(float $amount, string $currency): string
    {
        $amount = round(abs($amount), 2);
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);

        $words = self::spellArabic($whole);
        $currencyLabel = self::currencyLabelAr($currency);
        $text = 'فقط '.$words.' '.$currencyLabel;

        if ($fraction > 0) {
            $fractionLabel = self::fractionLabelAr($currency);
            $text .= ' و '.self::spellArabic($fraction).' '.$fractionLabel;
        }

        return $text.' لا غير';
    }

    private static function spellArabic(int $number): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter('ar', NumberFormatter::SPELLOUT);
            $spelled = $formatter->format($number);

            if (is_string($spelled) && $spelled !== '') {
                return $spelled;
            }
        }

        return (string) $number;
    }

    private static function currencyLabelAr(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => 'دولار',
            'AED' => 'درهم',
            'IQD' => 'دينار',
            'EUR' => 'يورو',
            default => $currency,
        };
    }

    private static function fractionLabelAr(string $currency): string
    {
        return match (strtoupper($currency)) {
            'IQD' => 'فلس',
            'AED' => 'فلس',
            'EUR' => 'سنت',
            default => 'سنت',
        };
    }

    public static function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'AED' => 'د.إ',
            'IQD' => 'د.ع',
            'EUR' => '€',
            default => strtoupper($currency),
        };
    }
}
