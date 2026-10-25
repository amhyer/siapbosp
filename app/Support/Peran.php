<?php

namespace App\Support;

use App\Models\User;

/**
 * Pemisahan tugas antar peran (R-09).
 *
 * Sebelumnya `dinas` dan `superadmin` nyaris setara pada pengelolaan akun:
 * seorang admin Dinas dapat mereset sandi sesama akun Dinas, sehingga satu
 * akun Dinas yang bocor bisa mengambil alih akun Dinas lain (dan seluruh data
 * kota). Sekarang:
 *
 * - `superadmin`  : administrasi teknis — semua akun, termasuk sesama
 *                   `superadmin` dan `dinas`.
 * - `dinas`       : operasional — hanya akun sekolah (`operator`, `kepsek`).
 * - `operator`/`kepsek` : tidak mengelola akun sama sekali.
 *
 * Ini belum Policy/Gate penuh (lihat R-09 di docs/AUDIT-KEAMANAN.md), tetapi
 * menutup jalur eskalasi horizontal yang paling berbahaya.
 */
class Peran
{
    /** Boleh aktor mengelola (ubah/reset/nonaktifkan/hapus) akun target? */
    public static function bolehKelola(?User $aktor, User $target): bool
    {
        if (! $aktor) {
            return false;
        }

        if ($aktor->role === 'superadmin') {
            return true;
        }

        if ($aktor->role !== 'dinas') {
            return false;
        }

        return in_array($target->role, ['operator', 'kepsek'], true);
    }

    /** Boleh aktor membuat/menetapkan akun dengan peran tertentu? */
    public static function bolehMenetapkan(?User $aktor, string $peran): bool
    {
        if (! $aktor) {
            return false;
        }

        return match ($peran) {
            'superadmin', 'dinas' => $aktor->role === 'superadmin',
            'operator', 'kepsek' => in_array($aktor->role, ['superadmin', 'dinas'], true),
            default => false,
        };
    }

    /** Pesan penolakan yang seragam. */
    public static function pesanTolak(string $peranTarget): string
    {
        return $peranTarget === 'superadmin' || $peranTarget === 'dinas'
            ? 'Hanya superadmin yang boleh mengelola akun superadmin/dinas.'
            : 'Anda tidak berhak mengelola akun ini.';
    }
}
