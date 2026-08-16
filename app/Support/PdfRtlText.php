<?php

namespace App\Support;

use ArPHP\I18N\Arabic;

final class PdfRtlText
{
    private static ?Arabic $engine = null;

    public static function shape(?string $text): string
    {
        $text = (string) $text;

        if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        return self::engine()->utf8Glyphs($text, 2000, false);
    }

    /**
     * @template T of array<mixed>
     *
     * @param  T  $data
     * @return T
     */
    public static function shapeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::shape($value);
            } elseif (is_array($value)) {
                $data[$key] = self::shapeArray($value);
            }
        }

        return $data;
    }

    private static function engine(): Arabic
    {
        return self::$engine ??= new Arabic;
    }
}
