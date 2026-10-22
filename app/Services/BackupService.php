<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use ZipArchive;

/**
 * Backup data sekolah: berkas SQLite + berkas konfigurasi launcher
 * (tanpa token) + manifest ringkas.
 */
class BackupService
{
    /** Unduh backup dalam bentuk ZIP. */
    public static function unduh()
    {
        $nama = 'siapbosp-backup-'.auth()->user()->school?->npsn.'-'.now()->format('Ymd-His').'.zip';

        return Response::streamDownload(function () {
            $tmp = tempnam(sys_get_temp_dir(), 'backup');
            $zip = new ZipArchive;
            $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // 1. Database
            $db = config('database.connections.sqlite.database');
            if ($db && is_file($db)) {
                $zip->addFile($db, 'database.sqlite');
            }

            // 2. Berkas konfigurasi launcher (token disamarkan)
            foreach (glob(base_path('launcher-windows/*.conf')) ?: [] as $conf) {
                $isi = (string) file_get_contents($conf);
                $isi = preg_replace('/^TOKEN=.*$/mi', 'TOKEN=(disembunyikan)', $isi);
                $zip->addFromString('launcher-windows/'.basename($conf), $isi);
            }

            // 3. Manifest
            $sekolah = auth()->user()->school;
            $manifest = [
                'aplikasi' => 'SiapBOSP',
                'waktu' => now()->toDateTimeString(),
                'npsn' => $sekolah?->npsn,
                'sekolah' => $sekolah?->nama,
                'transaksi' => (int) DB::table('kas_transactions')->where('school_id', auth()->user()->school_id)->count(),
                'rkas' => (int) DB::table('rkas_headers')->where('school_id', auth()->user()->school_id)->count(),
                'catatan' => 'Simpan berkas ini di tempat aman. Isi database.sqlite dapat dipulihkan dengan menyalinnya ke folder database/ aplikasi.',
            ];
            $zip->addFromString('MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $zip->close();
            readfile($tmp);
            @unlink($tmp);
        }, $nama, ['Content-Type' => 'application/zip']);
    }
}
