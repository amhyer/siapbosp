<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

/**
 * Netralisir Formula/CSV Injection (temuan K-07).
 * Murni fungsi, tidak butuh basis data.
 */
class CsvTest extends TestCase
{
    public function test_sel_menetralkan_formula_yang_dieksekusi_excel(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://jahat.test\")", Csv::sel('=HYPERLINK("http://jahat.test")'));
        $this->assertSame("'+62 812 0000", Csv::sel('+62 812 0000'));
        $this->assertSame("'-100", Csv::sel('-100'));
        $this->assertSame("'@SUM(A1:A9)", Csv::sel('@SUM(A1:A9)'));
        $this->assertSame("'\tcmd", Csv::sel("\tcmd"));
        $this->assertSame("'\rteks", Csv::sel("\rteks"));
    }

    public function test_sel_tidak_mengubah_teks_biasa_dan_nilai_non_string(): void
    {
        $this->assertSame('Kertas HVS A4', Csv::sel('Kertas HVS A4'));
        $this->assertSame('', Csv::sel(''));
        $this->assertSame(1000, Csv::sel(1000));
        $this->assertSame(0, Csv::sel(0));
        $this->assertNull(Csv::sel(null));
        $this->assertSame(3.5, Csv::sel(3.5));
    }

    public function test_baris_menetralkan_sel_teks_saja(): void
    {
        $this->assertSame(
            ['No', "'=cmd", 5000, null],
            Csv::baris(['No', '=cmd', 5000, null])
        );
    }
}
