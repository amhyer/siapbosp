<?php

namespace App\Support;

/**
 * Netralisir Formula/CSV Injection pada berkas ekspor (CSV & XLSX).
 *
 * Excel/LibreOffice mengeksekusi sel yang diawali `=`, `+`, `-`, `@`, TAB, atau
 * CR sebagai formula. Nilai seperti uraian belanja, keterangan transaksi, atau
 * nama sekolah berasal dari isian pengguna, sehingga seorang operator bisa
 * menanamkan `=WEBSERVICE(...)` / `=HYPERLINK(...)` yang berjalan di komputer
 * Dinas saat berkas unduhan dibuka.
 */
class Csv
{
    /** @var list<string> Karakter pembuka yang dianggap formula oleh Excel. */
    private const BERBAHAYA = ['=', '+', '-', '@', "\t", "\r"];

    /** Netralkan satu sel; hanya nilai teks yang perlu diawali petik tunggal. */
    public static function sel(mixed $nilai): mixed
    {
        if (! is_string($nilai) || $nilai === '') {
            return $nilai;
        }

        return in_array($nilai[0], self::BERBAHAYA, true) ? "'".$nilai : $nilai;
    }

    /**
     * Netralkan seluruh sel dalam satu baris.
     *
     * @param  array<int, mixed>  $baris
     * @return array<int, mixed>
     */
    public static function baris(array $baris): array
    {
        return array_map(fn ($nilai) => self::sel($nilai), $baris);
    }
}
