<?php

namespace App\Support;

use App\Enums\Currency;

final class AmountInWords
{
    /**
     * @return array{arabic: string, kurdish: string}
     */
    public static function both(float|string $amount, string $currency): array
    {
        return [
            'arabic' => self::arabic($amount, $currency),
            'kurdish' => self::kurdish($amount, $currency),
        ];
    }

    public static function arabic(float|string $amount, string $currency): string
    {
        [$major, $minor] = self::split($amount);
        $code = self::currency($currency);

        $majorWords = self::arabicInteger($major);
        $majorLabel = self::arabicCurrencyName($code, $major);
        $parts = [trim($majorWords.' '.$majorLabel)];

        if ($minor > 0) {
            $parts[] = 'و '.trim(self::arabicInteger($minor).' '.self::arabicFractionName($code, $minor));
        }

        return 'فقط '.trim(implode(' ', $parts)).' لا غير';
    }

    public static function kurdish(float|string $amount, string $currency): string
    {
        [$major, $minor] = self::split($amount);
        $code = self::currency($currency);

        $majorWords = self::kurdishInteger($major);
        $majorLabel = self::kurdishCurrencyName($code);
        $parts = [trim($majorWords.' '.$majorLabel)];

        if ($minor > 0) {
            $parts[] = 'و '.trim(self::kurdishInteger($minor).' '.self::kurdishFractionName($code));
        }

        return 'تەنها '.trim(implode(' ', $parts));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function split(float|string $amount): array
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        [$major, $minor] = explode('.', $normalized);

        return [(int) $major, (int) $minor];
    }

    private static function currency(string $currency): Currency
    {
        return Currency::tryFrom(strtoupper($currency)) ?? Currency::USD;
    }

    private static function arabicInteger(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $scales = [
            1_000_000_000 => ['مليار', 'ملياران', 'مليارات', 'ملياراً'],
            1_000_000 => ['مليون', 'مليونان', 'ملايين', 'مليوناً'],
            1_000 => ['ألف', 'ألفان', 'آلاف', 'ألفاً'],
        ];

        $parts = [];
        foreach ($scales as $value => $forms) {
            $count = intdiv($number, $value);
            if ($count === 0) {
                continue;
            }
            $number %= $value;
            $parts[] = self::arabicScale($count, $forms);
        }

        if ($number > 0) {
            $parts[] = self::arabicBelowThousand($number);
        }

        return implode(' و', $parts);
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string}  $forms
     */
    private static function arabicScale(int $count, array $forms): string
    {
        [$singular, $dual, $plural, $accusative] = $forms;

        if ($count === 1) {
            return $singular;
        }
        if ($count === 2) {
            return $dual;
        }
        if ($count >= 3 && $count <= 10) {
            return self::arabicBelowThousand($count).' '.$plural;
        }

        return self::arabicBelowThousand($count).' '.$accusative;
    }

    private static function arabicBelowThousand(int $number): string
    {
        $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
        $teens = ['عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $hundreds = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

        $parts = [];
        $hundred = intdiv($number, 100);
        $rest = $number % 100;
        if ($hundred > 0) {
            $parts[] = $hundreds[$hundred];
        }

        if ($rest === 0) {
            return implode(' و', $parts);
        }
        if ($rest < 10) {
            $parts[] = $ones[$rest];
        } elseif ($rest < 20) {
            $parts[] = $teens[$rest - 10];
        } else {
            $ten = intdiv($rest, 10);
            $one = $rest % 10;
            $parts[] = $one > 0 ? $ones[$one].' و'.$tens[$ten] : $tens[$ten];
        }

        return implode(' و', $parts);
    }

    private static function arabicCurrencyName(Currency $currency, int $count): string
    {
        return match ($currency) {
            Currency::USD => self::arabicNoun($count, 'دولار أمريكي', 'دولاران أمريكيان', 'دولارات أمريكية'),
            Currency::AED => self::arabicNoun($count, 'درهم إماراتي', 'درهمان إماراتيان', 'دراهم إماراتية'),
            Currency::IQD => self::arabicNoun($count, 'دينار عراقي', 'ديناران عراقيان', 'دنانير عراقية'),
            Currency::EUR => self::arabicNoun($count, 'يورو', 'يوروان', 'يوروهات'),
        };
    }

    private static function arabicFractionName(Currency $currency, int $count): string
    {
        return match ($currency) {
            Currency::USD, Currency::EUR => self::arabicNoun($count, 'سنت', 'سنتان', 'سنتات'),
            Currency::AED, Currency::IQD => self::arabicNoun($count, 'فلس', 'فلسان', 'فلوس'),
        };
    }

    private static function arabicNoun(int $count, string $singular, string $dual, string $plural): string
    {
        if ($count === 1) {
            return $singular;
        }
        if ($count === 2) {
            return $dual;
        }
        if ($count >= 3 && $count <= 10) {
            return $plural;
        }

        return $singular;
    }

    private static function kurdishInteger(int $number): string
    {
        if ($number === 0) {
            return 'سفر';
        }

        $scales = [
            1_000_000_000 => 'ملیار',
            1_000_000 => 'ملیۆن',
            1_000 => 'هەزار',
        ];

        $parts = [];
        foreach ($scales as $value => $label) {
            $count = intdiv($number, $value);
            if ($count === 0) {
                continue;
            }
            $number %= $value;
            $parts[] = $count === 1 ? $label : self::kurdishBelowThousand($count).' '.$label;
        }

        if ($number > 0) {
            $parts[] = self::kurdishBelowThousand($number);
        }

        return implode(' و ', $parts);
    }

    private static function kurdishBelowThousand(int $number): string
    {
        $ones = ['', 'یەک', 'دوو', 'سێ', 'چوار', 'پێنج', 'شەش', 'حەوت', 'هەشت', 'نۆ'];
        $teens = ['دە', 'یازدە', 'دوازدە', 'سێزدە', 'چواردە', 'پازدە', 'شازدە', 'حەڤدە', 'هەژدە', 'نۆزدە'];
        $tens = ['', 'دە', 'بیست', 'سی', 'چل', 'پەنجا', 'شەست', 'حەفتا', 'هەشتا', 'نەوەد'];

        $parts = [];
        $hundred = intdiv($number, 100);
        $rest = $number % 100;
        if ($hundred === 1) {
            $parts[] = 'سەد';
        } elseif ($hundred > 1) {
            $parts[] = $ones[$hundred].' سەد';
        }

        if ($rest === 0) {
            return implode(' و ', $parts);
        }
        if ($rest < 10) {
            $parts[] = $ones[$rest];
        } elseif ($rest < 20) {
            $parts[] = $teens[$rest - 10];
        } else {
            $ten = intdiv($rest, 10);
            $one = $rest % 10;
            $parts[] = $one > 0 ? $tens[$ten].' و '.$ones[$one] : $tens[$ten];
        }

        return implode(' و ', $parts);
    }

    private static function kurdishCurrencyName(Currency $currency): string
    {
        return match ($currency) {
            Currency::USD => 'دۆلاری ئەمریکی',
            Currency::AED => 'درهەمی ئیماراتی',
            Currency::IQD => 'دیناری عێراقی',
            Currency::EUR => 'یۆرۆ',
        };
    }

    private static function kurdishFractionName(Currency $currency): string
    {
        return match ($currency) {
            Currency::USD, Currency::EUR => 'سەنت',
            Currency::AED, Currency::IQD => 'فڵس',
        };
    }
}
