<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Backup data untuk diunduh (R-10).
 *
 * Berisi berkas SQLite + konfigurasi launcher (token disamarkan) + manifest.
 * Bila `BACKUP_PASSWORD` diisi, entri basis data dienkripsi AES-256 di dalam
 * ZIP sehingga berkas yang tersalin ke flashdisk/email tidak langsung terbaca.
 * Berkas sementara selalu `chmod 0600`.
 */
class BackupService
{
    /** Sandi enkripsi backup (kosong = tidak dienkripsi). */
    public static function sandi(): string
    {
        return trim((string) env('BACKUP_PASSWORD', ''));
    }

    /** Unduh backup dalam bentuk ZIP. */
    public static function unduh()
    {
        $npsn = preg_replace('/[^A-Za-z0-9]+/', '', (string) (auth()->user()->school?->npsn ?? '')) ?: 'pusat';
        $nama = 'siapbosp-backup-'.$npsn.'-'.now()->format('Ymd-His').'.zip';

        $tmp = tempnam(sys_get_temp_dir(), 'backup_').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Berkas backup tidak bisa dibuat di folder sementara server.');
        }

        $password = self::sandi();

        // 1. Basis data
        $db = config('database.connections.sqlite.database');
        $adaDb = $db && is_file($db);
        if ($adaDb) {
            $zip->addFile($db, 'database.sqlite');
        }

        // 2. Berkas konfigurasi launcher (token disamarkan)
        foreach (glob(base_path('launcher-windows/*.conf')) ?: [] as $conf) {
            $isi = (string) file_get_contents($conf);
            // Samarkan token pada format apa pun: TOKEN=, token =, "TOKEN = ...".
            $isi = preg_replace('/^\s*TOKEN\s*=.*$/mi', 'TOKEN=(disembunyikan)', $isi);
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
            'terenkripsi' => $password !== '',
            'catatan' => $password !== ''
                ? 'database.sqlite di dalam ZIP dienkripsi AES-256 (sandi di BACKUP_PASSWORD). Simpan sandi terpisah dari berkas ini.'
                : 'Simpan berkas ini di tempat aman. Isi database.sqlite dapat dipulihkan dengan menyalinnya ke folder database/ aplikasi.',
        ];
        $zip->addFromString('MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 4. Enkripsi entri basis data bila sandi diatur.
        if ($password !== '' && $adaDb) {
            $ok = $zip->setEncryptionName('database.sqlite', ZipArchive::EM_AES_256, $password);
            if (! $ok) {
                $zip->close();
                @unlink($tmp);
                abort(500, 'Enkripsi backup gagal: pustaka ZIP server tidak mendukung AES-256. '
                    .'Kosongkan BACKUP_PASSWORD atau perbarui PHP/libzip.');
            }
        }

        $zip->close();
        @chmod($tmp, 0600);

        return response()->download($tmp, $nama, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Bungkus satu berkas (mis. dump .sql) menjadi ZIP berpassword.
     * Mengembalikan path ZIP, atau null bila enkripsi tidak tersedia.
     */
    public static function bungkusTerenkripsi(string $path, string $namaDiZip): ?string
    {
        $password = self::sandi();
        if ($password === '') {
            return null;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'backup_').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $zip->addFile($path, $namaDiZip);
        if (! $zip->setEncryptionName($namaDiZip, ZipArchive::EM_AES_256, $password)) {
            $zip->close();
            @unlink($zipPath);

            return null;
        }
        $zip->close();
        @chmod($zipPath, 0600);

        return $zipPath;
    }
}
