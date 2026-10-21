<?php

namespace App\Support;

/**
 * Label & ikon sumber dana BOSP (dipakai di RKAS, laporan, dan SPJ).
 *
 * Nama sumber dana pada ARKAS datang sebagai teks bebas, misalnya
 * "BOS Reguler", "BOSP PAUD Reguler", "BOS Afirmasi" (ARKAS 4.2.16+),
 * "SiLPA BOS Afirmasi", "BOS Kinerja", "BOSP Daerah", "Lainnya"/Banpem.
 */
class SumberDana
{
    /** @var array<string,array{label:string,ikon:string,warna:string}> */
    public const DAFTAR = [
        'reguler' => ['label' => 'BOS Reguler', 'ikon' => '🏫', 'warna' => 'bg-emerald-100 text-emerald-700'],
        'afirmasi' => ['label' => 'BOSP Afirmasi', 'ikon' => '🌟', 'warna' => 'bg-violet-100 text-violet-700'],
        'bosda' => ['label' => 'BOSP Daerah', 'ikon' => '🏛️', 'warna' => 'bg-sky-100 text-sky-700'],
        'kinerja' => ['label' => 'BOS Kinerja', 'ikon' => '📈', 'warna' => 'bg-amber-100 text-amber-700'],
        'silpa' => ['label' => 'SILPA', 'ikon' => '💰', 'warna' => 'bg-slate-100 text-slate-600'],
        'lainnya' => ['label' => 'Lainnya', 'ikon' => '🧾', 'warna' => 'bg-neutral-100 text-neutral-600'],
    ];

    /** Deteksi kunci sumber dana dari teks bebas. */
    public static function kunci(?string $nama): string
    {
        $s = mb_strtolower(trim((string) $nama));
        if ($s === '') {
            return 'lainnya';
        }
        if (str_contains($s, 'silpa')) {
            return 'silpa';
        }
        if (str_contains($s, 'afirmasi')) {
            return 'afirmasi';
        }
        if (str_contains($s, 'kinerja')) {
            return 'kinerja';
        }
        if (str_contains($s, 'daerah') || str_contains($s, 'bosda')) {
            return 'bosda';
        }
        if (str_contains($s, 'reguler')) {
            return 'reguler';
        }

        return 'lainnya';
    }

    /** @return array{label:string,ikon:string,warna:string} */
    public static function info(?string $nama): array
    {
        $kunci = self::kunci($nama);
        if ($kunci === 'lainnya' && ! in_array(mb_strtolower(trim((string) $nama)), ['lainnya', 'lain'], true)) {
            $teks = trim((string) $nama);

            return ['label' => $teks !== '' ? $teks : 'Lainnya', 'ikon' => '🧾', 'warna' => 'bg-neutral-100 text-neutral-600'];
        }

        return self::DAFTAR[$kunci];
    }

    public static function label(?string $nama): string
    {
        return self::info($nama)['label'];
    }

    public static function ikon(?string $nama): string
    {
        return self::info($nama)['ikon'];
    }

    /** Badge HTML siap pakai di Blade. */
    public static function badge(?string $nama): string
    {
        $i = self::info($nama);

        return '<span class="badge '.$i['warna'].'">'.$i['ikon'].' '.$i['label'].'</span>';
    }
}
