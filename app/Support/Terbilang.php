<?php

namespace App\Support;

/** Konversi angka ke kalimat terbilang Bahasa Indonesia (untuk lembar pengesahan LPJ). */
class Terbilang
{
    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function of(int $n): string
    {
        if ($n === 0) {
            return 'nol';
        }
        $negatif = $n < 0;
        $n = abs($n);
        // Normalisasi: ucap() menyisakan spasi ganda untuk kelipatan penuh (100, 50 juta, dst.)
        $teks = trim(preg_replace('/\s+/', ' ', self::ucap($n)));

        return ($negatif ? 'minus ' : '').$teks.' rupiah';
    }

    private static function ucap(int $n): string
    {
        if ($n < 12) {
            return ' '.self::SATUAN[$n];
        }
        if ($n < 20) {
            return self::ucap($n - 10).' belas';
        }
        if ($n < 100) {
            return self::ucap(intdiv($n, 10)).' puluh'.self::ucap($n % 10);
        }
        if ($n < 200) {
            return ' seratus'.self::ucap($n - 100);
        }
        if ($n < 1000) {
            return self::ucap(intdiv($n, 100)).' ratus'.self::ucap($n % 100);
        }
        if ($n < 2000) {
            return ' seribu'.self::ucap($n - 1000);
        }
        if ($n < 1000000) {
            return self::ucap(intdiv($n, 1000)).' ribu'.self::ucap($n % 1000);
        }
        if ($n < 1000000000) {
            return self::ucap(intdiv($n, 1000000)).' juta'.self::ucap($n % 1000000);
        }
        if ($n < 1000000000000) {
            return self::ucap(intdiv($n, 1000000000)).' miliar'.self::ucap($n % 1000000000);
        }

        return self::ucap(intdiv($n, 1000000000000)).' triliun'.self::ucap($n % 1000000000000);
    }
}
