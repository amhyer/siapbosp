<?php

namespace App\Support;

/**
 * Kebijakan kata sandi (R-01).
 *
 * Dipakai oleh pembuatan akun, reset sandi Dinas, ganti sandi mandiri, dan
 * impor pengguna — supaya tidak ada jalur yang lolos dengan sandi lemah
 * (sebelumnya `min:6` dan impor CSV memakai sandi bawaan "password").
 */
class Sandi
{
    /** Panjang minimum yang diwajibkan. */
    public const MIN = 10;

    /**
     * Sandi yang paling sering dipakai pada kebocoran data / sudah ada di
     * daftar tebakan pertama. Dibandingkan tanpa peduli huruf besar-kecil.
     */
    public const UMUM = [
        'password', 'password1', 'password123', 'passw0rd', 'p@ssw0rd',
        '12345678', '123456789', '1234567890', 'qwerty123', 'qwerty12345',
        'iloveyou', 'administrator', 'operator123', 'kepsek123', 'dinaspendidikan',
        'siapbosp', 'siapbosp123', 'bosp2026', 'bosp12345', 'rahasia123',
        'kotamakassar', 'sulawesiselatan', 'bendahara123',
    ];

    /**
     * Periksa kekuatan sandi. Mengembalikan pesan kesalahan, atau `null` bila
     * sandi memenuhi kebijakan.
     */
    public static function masalah(?string $sandi, ?string $email = null, ?string $nama = null): ?string
    {
        $s = (string) $sandi;

        if (mb_strlen($s) < self::MIN) {
            return 'Kata sandi minimal '.self::MIN.' karakter.';
        }

        if (preg_match('/^(.)\1+$/u', $s)) {
            return 'Kata sandi tidak boleh satu karakter yang diulang.';
        }

        if (in_array(mb_strtolower($s), self::UMUM, true)) {
            return 'Kata sandi terlalu umum dan mudah ditebak. Pilih yang lain.';
        }

        if (! preg_match('/\p{L}/u', $s) || ! preg_match('/\d/', $s)) {
            return 'Kata sandi harus memuat huruf dan angka.';
        }

        $email = mb_strtolower(trim((string) $email));
        $lokal = trim(explode('@', $email)[0] ?? '');
        if (mb_strlen($lokal) >= 3 && str_contains(mb_strtolower($s), $lokal)) {
            return 'Kata sandi tidak boleh memuat bagian dari alamat email.';
        }

        $nama = mb_strtolower(trim((string) $nama));
        if (mb_strlen($nama) >= 4 && str_contains(mb_strtolower($s), $nama)) {
            return 'Kata sandi tidak boleh memuat nama akun.';
        }

        return null;
    }

    /** Sandi acak yang memenuhi kebijakan (14 karakter, tanpa huruf ambigu). */
    public static function acak(): string
    {
        $kecil = 'abcdefghijkmnopqrstuvwxyz';
        $besar = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $angka = '23456789';
        $semua = $kecil.$besar.$angka;

        $s = $kecil[random_int(0, strlen($kecil) - 1)]
            .$besar[random_int(0, strlen($besar) - 1)]
            .$angka[random_int(0, strlen($angka) - 1)];

        while (strlen($s) < 14) {
            $s .= $semua[random_int(0, strlen($semua) - 1)];
        }

        return str_shuffle($s);
    }
}
