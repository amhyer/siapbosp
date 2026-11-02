<?php

if (! function_exists('rp')) {
    function rp($v): string
    {
        return 'Rp '.number_format((int) $v, 0, ',', '.');
    }
}

if (! function_exists('terbilang_rek')) {
    /** Rekursi internal terbilang — sisa 0 menghasilkan string kosong. */
    function terbilang_rek(int $angka): string
    {
        $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($angka === 0) {
            $t = '';
        } elseif ($angka < 12) {
            $t = $huruf[$angka];
        } elseif ($angka < 20) {
            $t = terbilang_rek($angka - 10).' belas';
        } elseif ($angka < 100) {
            $t = terbilang_rek(intdiv($angka, 10)).' puluh '.terbilang_rek($angka % 10);
        } elseif ($angka < 200) {
            $t = 'seratus '.terbilang_rek($angka - 100);
        } elseif ($angka < 1000) {
            $t = terbilang_rek(intdiv($angka, 100)).' ratus '.terbilang_rek($angka % 100);
        } elseif ($angka < 2000) {
            $t = 'seribu '.terbilang_rek($angka - 1000);
        } elseif ($angka < 1000000) {
            $t = terbilang_rek(intdiv($angka, 1000)).' ribu '.terbilang_rek($angka % 1000);
        } elseif ($angka < 1000000000) {
            $t = terbilang_rek(intdiv($angka, 1000000)).' juta '.terbilang_rek($angka % 1000000);
        } elseif ($angka < 1000000000000) {
            $t = terbilang_rek(intdiv($angka, 1000000000)).' miliar '.terbilang_rek($angka % 1000000000);
        } else {
            $t = terbilang_rek(intdiv($angka, 1000000000000)).' triliun '.terbilang_rek($angka % 1000000000000);
        }

        return trim(preg_replace('/\s+/', ' ', $t));
    }
}

if (! function_exists('terbilang')) {
    /** Angka → kata bahasa Indonesia (untuk kwitansi & dokumen resmi). Nol tetap "nol". */
    function terbilang($angka): string
    {
        $angka = (int) abs($angka);
        if ($angka === 0) {
            return 'nol';
        }

        return terbilang_rek($angka);
    }
}
