<?php

namespace App\Support;

/**
 * Pembaca/penulis berkas konfigurasi KEY=VALUE sederhana
 * (launcher-windows/akun-sekolah.conf, agen.conf, server.conf).
 */
class KonfigLokal
{
    /** Baca KEY=VALUE (abaikan komentar # dan baris kosong). */
    public static function baca(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
            $baris = trim($baris);
            if ($baris === '' || str_starts_with($baris, '#')) {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $baris, 2), 2, '');
            $out[trim($k)] = trim($v);
        }

        return $out;
    }

    /** Tulis agen.conf — dipakai pemasang agar Tarik-ARKAS langsung jalan. */
    public static function tulisAgen(string $path, string $server, string $token, ?int $tahun): void
    {
        @mkdir(dirname($path), 0777, true);
        $isi = "# Dibuat otomatis oleh pemasang SiapBOSP (siapbos:pasang-lokal).\n"
            ."# JANGAN disebar: token ini kunci kirim data ARKAS ke aplikasi ini.\n"
            .'SERVER='.$server."\n"
            .'TOKEN='.$token."\n"
            .'TAHUN='.($tahun ?: '')."\n";
        file_put_contents($path, $isi);
    }

    /** Jadikan path absolut terhadap base_path bila masih relatif. */
    public static function absolut(string $path): string
    {
        return preg_match('#^([A-Za-z]:[\\\\/]|[\\\\/])#', $path) ? $path : base_path($path);
    }
}
