<?php

namespace App\Support;

final class PdfRtlText
{
    private static mixed $engine = null;

    public static function shape(?string $text): string
    {
        $text = (string) $text;

        if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        $engine = self::engine();
        if ($engine === null) {
            return $text;
        }

        return $engine->utf8Glyphs($text, 2000, false);
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

    private static function engine(): ?object
    {
        if (self::$engine !== null) {
            return self::$engine;
        }

        if (! class_exists(\ArPHP\I18N\Arabic::class)) {
            return null;
        }

        return self::$engine = new \ArPHP\I18N\Arabic;
    }
}
