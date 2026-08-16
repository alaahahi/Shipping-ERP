<?php

namespace Tests\Unit;

use App\Support\PdfRtlText;
use Tests\TestCase;

class PdfRtlTextTest extends TestCase
{
    public function test_it_shapes_arabic_for_pdf_engines(): void
    {
        $source = 'كشف الحساب';
        $shaped = PdfRtlText::shape($source);

        $this->assertNotSame('', $shaped);
        $this->assertNotSame($source, $shaped);
    }

    public function test_it_leaves_latin_and_numbers_unchanged(): void
    {
        $this->assertSame('JV-202607-0021', PdfRtlText::shape('JV-202607-0021'));
        $this->assertSame('1100.00', PdfRtlText::shape('1100.00'));
    }
}
