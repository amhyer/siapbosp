<?php

namespace App\Services;

use App\Http\Controllers\UnduhAplikasiController;
use App\Models\School;

/**
 * Jembatan tombol "Tarik Data ARKAS" (1 klik) di dalam aplikasi portabel
 * (Utility → Profil Sekolah).
 *
 * Menjalankan agen TarikArkas yang terbundel (`agen/TarikArkas/node.exe`
 * mode `lokal`): agen membaca database ARKAS desktop di laptop ini
 * (read-only) dan membangun berkas Excel, lalu aplikasi mengimpornya
 * sendiri lewat ArkasImportService — aturan impor sama persis dengan
 * unggahan web (idempoten, tidak ada yang dobel atau terhapus).
 */
class TarikArkasService
{
    /** Batas detik menunggu agen membaca ARKAS dan menulis Excel. */
    public const TENGGAGA_DETIK = 300;

    /** Agen tersedia: aplikasi portabel + runtime agen terbundel. */
    public static function tersedia(): bool
    {
        return UnduhAplikasiController::isPortable()
            && is_file(base_path('agen/TarikArkas/node.exe'))
            && is_file(base_path('agen/TarikArkas/tarik.cjs'));
    }

    /** Lokasi database ARKAS desktop di laptop ini (%AppData%\Roaming\Arkas\arkas.db). */
    public static function pathDb(): string
    {
        $calon = [];
        $appdata = getenv('APPDATA');
        if ($appdata) {
            $calon[] = rtrim($appdata, '\\/').'\\Arkas\\arkas.db';
        }
        $home = getenv('USERPROFILE');
        if ($home) {
            $calon[] = rtrim($home, '\\/').'\\AppData\\Roaming\\Arkas\\arkas.db';
        }
        // `php artisan serve` bisa mewarisi env kosong (Laravel menurunkan $_ENV;
        // variables_order tanpa "E" membuatnya kosong) — turunkan profil user dari
        // lokasi pemasangan sendiri: ...\\AppData\\Local\\... → ...\\AppData\\Local\\...
        $profil = self::profilDariLokasiPemasangan();
        if ($profil !== '') {
            $calon[] = $profil.'\\AppData\\Roaming\\Arkas\\arkas.db';
        }
        foreach ($calon as $c) {
            if ($c !== '' && is_file($c)) {
                return $c;
            }
        }

        return $calon[0] ?? '';
    }

    /** Profildir user dari base_path: C:\Users\X\AppData\Local\... → C:\Users\X. */
    protected static function profilDariLokasiPemasangan(): string
    {
        $dasar = str_replace('/', '\\', base_path());
        $pos = stripos($dasar, '\\AppData\\');
        if ($pos <= 0) {
            return '';
        }

        return rtrim(substr($dasar, 0, $pos), '\\');
    }

    /** Sisipkan --db eksplisit agar agen tidak bergantung pada env proses server. */
    protected static function denganDb(array $perintah): array
    {
        $db = self::dbAda() ? self::pathDb() : '';

        // Di akhir: argv[2] agen harus tetap subperintah (info/lokal/tes).
        return $db !== '' ? [...$perintah, '--db', $db] : $perintah;
    }

    public static function dbAda(): bool
    {
        $path = self::pathDb();

        return $path !== '' && is_file($path);
    }

    /** Status untuk kartu di Profil — null bila bukan aplikasi portabel. */
    public static function status(): ?array
    {
        if (! self::tersedia()) {
            return null;
        }
        $info = null;
        if (self::dbAda()) {
            $out = self::jalankan(self::denganDb(['info', '--json']));
            $info = json_decode($out, true);
        }

        return [
            'db_ada' => self::dbAda(),
            'path_db' => self::pathDb(),
            'npsn' => is_array($info) ? ($info['npsnFile'] ?? null) : null,
        ];
    }

    /** Tarik data ARKAS (1 klik): bangun Excel via agen, lalu impor ke sekolah. */
    public static function tarik(int $schoolId, int $userId, int $tahun): array
    {
        $berkas = self::eksporDb($tahun);
        $keluar = $berkas['path'];
        try {
            $sekolah = School::find($schoolId);
            if ($sekolah && $sekolah->npsn && ! empty($berkas['npsn'])
                && (string) $sekolah->npsn !== (string) $berkas['npsn']) {
                throw new \RuntimeException('Database ARKAS di laptop ini milik NPSN '.$berkas['npsn']
                    .', tetapi profil sekolah Anda NPSN '.$sekolah->npsn.'.'
                    .' Betulkan Profil sekolah dulu, atau pakai unggah Excel manual di halaman Impor ARKAS.');
            }
            $lap = ArkasImportService::import($keluar, $schoolId, $userId, $tahun);
            $lap['npsn_arkas'] = $berkas['npsn'] ?? null;

            return $lap;
        } finally {
            @unlink($keluar);
        }
    }

    /**
     * Jalankan agen mode `lokal` → berkas Excel ARKAS (tanpa impor).
     *
     * Dipakai juga untuk "Cek Ulang Integritas Impor" — pemanggil wajib
     * menghapus berkas hasilnya.
     *
     * @return array{path:string,npsn:?string}
     */
    public static function eksporDb(int $tahun): array
    {
        if (! self::tersedia()) {
            throw new \RuntimeException('Agen Tarik ARKAS hanya tersedia pada aplikasi portabel yang terpasang di laptop sekolah.');
        }
        $keluar = storage_path('app/tarik-arkas-'.uniqid().'.xlsx');
        $out = self::jalankan(self::denganDb(['lokal', '--json', '--tahun', (string) $tahun, '--keluar', $keluar]));
        $hasil = json_decode($out, true);
        if (! is_array($hasil) || empty($hasil['ok'])) {
            @unlink($keluar);

            throw new \RuntimeException(self::pesanRamah(is_array($hasil) ? ($hasil['pesan'] ?? null) : null, $out));
        }
        if (! is_file($keluar)) {
            throw new \RuntimeException('Agen tidak menghasilkan berkas Excel.');
        }

        return ['path' => $keluar, 'npsn' => $hasil['npsn'] ?? null];
    }

    /** Jalankan agen bundel (node.exe) dan balikkan keluaran stdout-nya. */
    protected static function jalankan(array $perintah): string
    {
        $argum = [
            base_path('agen/TarikArkas/node.exe'),
            base_path('agen/TarikArkas/tarik.cjs'),
            ...$perintah,
        ];
        $cmd = implode(' ', array_map('escapeshellarg', $argum));
        $pip = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proses = proc_open($cmd, $pip, $pip, base_path());
        if (! is_resource($proses)) {
            throw new \RuntimeException('Tidak bisa menjalankan agen TarikArkas.');
        }
        fclose($pip[0]);
        stream_set_blocking($pip[1], false);
        stream_set_blocking($pip[2], false);
        $stdout = '';
        $stderr = '';
        $tenggat = microtime(true) + self::TENGGAGA_DETIK;
        while (true) {
            $chunk = [$pip[1], $pip[2]];
            $tulis = null;
            $khusus = null;
            $sisa = max(0, $tenggat - microtime(true));
            $n = @stream_select($chunk, $tulis, $khusus, 0, (int) (min($sisa, 0.5) * 1_000_000));
            if ($n === false) {
                break;
            }
            foreach ($chunk as $s) {
                $isi = (string) fread($s, 8192);
                if ($s === $pip[1]) {
                    $stdout .= $isi;
                } else {
                    $stderr .= $isi;
                }
            }
            $status = proc_get_status($proses);
            if (! $status['running']) {
                break;
            }
            if (microtime(true) > $tenggat) {
                proc_terminate($proses);
                fclose($pip[1]);
                fclose($pip[2]);
                proc_close($proses);
                throw new \RuntimeException('Agen TarikArkas tidak selesai dalam '.self::TENGGAGA_DETIK
                    .' detik. Coba sekali lagi, atau pakai unggah Excel manual di halaman Impor ARKAS.');
            }
        }
        // Proses selesai: habiskan sisa buffer sampai berakhir (EOF cepat setelah mati).
        stream_set_blocking($pip[1], true);
        stream_set_blocking($pip[2], true);
        $stdout .= (string) stream_get_contents($pip[1]);
        $stderr .= (string) stream_get_contents($pip[2]);
        fclose($pip[1]);
        fclose($pip[2]);
        proc_close($proses);

        return $stdout !== '' ? $stdout : $stderr;
    }

    /** Pesan gagal agen → kalimat yang enak dibaca operator. */
    protected static function pesanRamah(?string $pesan, string $out = ''): string
    {
        $pesan = trim((string) $pesan);
        if ($pesan === '') {
            $ekor = trim(strip_tags($out));
            if ($ekor !== '') {
                $pesan = mb_substr($ekor, 0, 300);
            }
        }
        if ($pesan === '' || stripos($pesan, 'SQLITE_BUSY') !== false
            || stripos($pesan, 'database is locked') !== false || stripos($pesan, 'unable to open') !== false) {
            return 'Aplikasi ARKAS sedang terbuka atau database-nya terkunci. Tutup dulu aplikasi ARKAS, lalu klik Tarik Data sekali lagi.';
        }
        if (str_contains($pesan, "\n")) {
            $pesan = str_replace("\n", ' — ', $pesan);
        }

        return $pesan;
    }
}
