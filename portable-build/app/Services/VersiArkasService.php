<?php

namespace App\Services;

use App\Http\Controllers\UnduhAplikasiController;

/**
 * Deteksi versi ARKAS desktop yang terpasang di laptop sekolah & kesiapan
 * data master untuk Juknis BOSP 2026 (Permendikdasmen No 8/2026).
 *
 * Sumber data: agen TarikArkas mode `info` (baca `app_config.version_app`,
 * tenggat penganggaran/penatausahaan, batas pergeseran/perubahan, whitelist
 * juknis) — hanya tersedia di aplikasi portabel.
 */
class VersiArkasService
{
    /** ARKAS 4 minimum agar Implementasi Juknis 8/2026 berlaku (resmi). */
    public const VERSI_MINIMUM = '4.2.18';

    /**
     * Ringkasan versi & kesiapan.
     *
     * @return array{portabel:bool,versi:?string,minimum:string,cukup:bool,info:array,pesan:?string}
     */
    public static function deteksi(): array
    {
        $portabel = UnduhAplikasiController::isPortable();
        $hasil = [
            'portabel' => $portabel,
            'versi' => null,
            'minimum' => self::VERSI_MINIMUM,
            'cukup' => false,
            'info' => [],
            'pesan' => null,
        ];

        if (! $portabel) {
            $hasil['pesan'] = 'Deteksi versi hanya bisa dilakukan di aplikasi portabel yang terpasang di laptop sekolah (server pusat tidak memiliki database ARKAS).';

            return $hasil;
        }

        $info = TarikArkasService::info();
        if (! $info) {
            $hasil['pesan'] = 'Agen Tarik ARKAS tidak merespons. Pastikan aplikasi ARKAS sudah pernah dibuka di laptop ini.';

            return $hasil;
        }

        $versi = $info['versi']['version_app'] ?? null;
        $hasil['versi'] = $versi;
        $hasil['info'] = $info['versi'] ?? [];
        $hasil['cukup'] = $versi ? version_compare((string) $versi, self::VERSI_MINIMUM, '>=') : false;
        if ($versi && ! $hasil['cukup']) {
            $hasil['pesan'] = 'Versi ARKAS di laptop ini masih '.$versi.' — Implementasi Juknis BOSP 2026 baru berlaku pada ARKAS 4.'.self::VERSI_MINIMUM.'. Perbarui ARKAS terlebih dahulu.';
        }

        return $hasil;
    }
}
