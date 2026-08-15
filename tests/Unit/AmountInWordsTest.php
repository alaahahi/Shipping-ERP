<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use Tests\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_arabic_and_kurdish_amount_words_for_usd(): void
    {
        $words = AmountInWords::both(2500, 'USD');

        $this->assertStringContainsString('ألفان', $words['arabic']);
        $this->assertStringContainsString('وخمسمائة', str_replace(' ', '', $words['arabic']));
        $this->assertStringContainsString('دولار', $words['arabic']);
        $this->assertStringContainsString('فقط', $words['arabic']);

        $this->assertStringContainsString('دوو هەزار', $words['kurdish']);
        $this->assertStringContainsString('پێنج سەد', $words['kurdish']);
        $this->assertStringContainsString('دۆلاری ئەمریکی', $words['kurdish']);
    }

    public function test_fraction_is_included_in_arabic(): void
    {
        $arabic = AmountInWords::arabic('10.50', 'USD');

        $this->assertStringContainsString('عشرة', $arabic);
        $this->assertStringContainsString('خمسون', $arabic);
        $this->assertStringContainsString('سنت', $arabic);
    }
}
