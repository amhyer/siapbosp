<?php

namespace App\Services;

use App\Http\Controllers\UnduhAplikasiController;
use App\Support\KonfigLokal;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Status & aksi sinkronisasi data sekolah ke website pusat (SiapBOSP).
 *
 * Identitas dibaca dari `launcher-windows/akun-sekolah.conf` (PUSAT_URL,
 * TOKEN, NPSN) — berkas yang dibuat saat unduh aplikasi per akun sekolah.
 */
class SinkronisasiService
{
    /** Konfigurasi akun sekolah (pusat) dari akun-sekolah.conf atau fallback config.json agen. */
    public static function conf(): array
    {
        $conf = KonfigLokal::baca(KonfigLokal::absolut('launcher-windows/akun-sekolah.conf'));
        if (! empty($conf['PUSAT_URL']) && ! empty($conf['TOKEN'])) {
            return $conf;
        }

        $appdata = getenv('APPDATA') ?: (getenv('USERPROFILE') ? rtrim((string) getenv('USERPROFILE'), '\\/').'\\AppData\\Roaming' : '');
        if ($appdata !== '') {
            $cfgAgen = rtrim($appdata, '\\/').'\\SiapBOS-Agen\\config.json';
            if (is_file($cfgAgen)) {
                $json = json_decode((string) file_get_contents($cfgAgen), true);
                if (is_array($json)) {
                    $conf['PUSAT_URL'] = $conf['PUSAT_URL'] ?? ($json['server'] ?? null);
                    $conf['TOKEN'] = $conf['TOKEN'] ?? ($json['token'] ?? null);
                    $conf['NPSN'] = $conf['NPSN'] ?? ($json['npsn'] ?? null);
                }
            }
        }

        return $conf;
    }

    /** Ringkasan status sinkronisasi untuk halaman Pengaturan. */
    public static function status(): array
    {
        $conf = self::conf();
        $terakhir = DB::table('sync_states')->where('kunci', 'terakhir_dorong')->value('nilai');
        $lokal = (int) DB::table('kas_transactions')
            ->where('school_id', auth()->user()->school_id)
            ->count();

        return [
            'punya_akun' => ! empty($conf['TOKEN']) && ! empty($conf['PUSAT_URL']),
            'pusat_url' => $conf['PUSAT_URL'] ?? null,
            'npsn' => $conf['NPSN'] ?? null,
            'token_ada' => ! empty($conf['TOKEN']),
            'terakhir_dorong' => $terakhir,
            'jumlah_transaksi_lokal' => $lokal,
            'portabel' => UnduhAplikasiController::isPortable(),
        ];
    }

    /** Periksa koneksi ke website pusat. */
    public static function uji(): array
    {
        $conf = self::conf();
        if (empty($conf['PUSAT_URL'])) {
            throw new \RuntimeException('Alamat pusat belum-known. Unduh "Identitas Sekolah" dari website pusat lalu simpan di folder launcher-windows (akun-sekolah.conf).');
        }
        $url = rtrim($conf['PUSAT_URL'], '/').'/api/v1/ping';
        try {
            $res = Http::timeout(10)->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'pesan' => 'Tidak bisa menghubungi pusat ('.$e->getMessage().').'];
        }
        if (! $res->successful()) {
            return ['ok' => false, 'pesan' => 'Pusat menjawab HTTP '.$res->status().'.'];
        }
        $data = $res->json();

        return ['ok' => true, 'pesan' => 'Pusat terhubung: '.($data['app'] ?? 'SiapBOSP').' · versi '.($data['versi'] ?? '?')];
    }

    /** Jalankan sinkronisasi ke pusat (idempoten). */
    public static function jalan(?int $tahun = null): array
    {
        $conf = self::conf();
        if (empty($conf['PUSAT_URL']) || empty($conf['TOKEN'])) {
            throw new \RuntimeException('Identitas pusat belum ada (akun-sekolah.conf). Unduh "Identitas Sekolah" dari website pusat.');
        }
        $tahun = $tahun ?? (int) session('tahun', now()->year);
        $kode = Artisan::call('siapbos:sync-push', ['--tahun' => $tahun]);
        $keluaran = trim(Artisan::output());
        if ($kode !== 0) {
            throw new \RuntimeException($keluaran !== '' ? $keluaran : 'Sinkronisasi ditolak pusat (kode '.$kode.').');
        }
        DB::table('sync_states')->updateOrInsert(['kunci' => 'terakhir_dorong'], [
            'nilai' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now(), 'created_at' => now(),
        ]);

        return ['pesan' => 'Sinkronisasi selesai. '.$keluaran];
    }

    /**
     * Dorong otomatis hasil tarikan ARKAS dari aplikasi desktop ke website pusat:
     * 1. Mengunggah berkas Excel hasil tarikan ARKAS ke /api/v1/arkas-push
     * 2. Menjalankan siapbos:sync-push (/api/v1/sync-push) untuk transaksi & RKAS lokal
     *
     * @return array{terkirim: bool, pesan: string}
     */
    public static function dorongOtomatisKeWebsite(string $pathExcel, int $tahun): array
    {
        $conf = self::conf();
        if (empty($conf['PUSAT_URL']) || empty($conf['TOKEN'])) {
            return [
                'terkirim' => false,
                'pesan' => 'belum terhubung ke website (akun-sekolah.conf belum diisi)',
            ];
        }

        $pusatUrl = rtrim((string) $conf['PUSAT_URL'], '/');
        $token = (string) $conf['TOKEN'];
        $catatan = [];
        $berhasil = false;

        if (is_file($pathExcel)) {
            try {
                $res = Http::timeout(120)
                    ->withToken($token)
                    ->attach('berkas', (string) file_get_contents($pathExcel), "arkas-{$tahun}.xlsx")
                    ->post("{$pusatUrl}/api/v1/arkas-push", ['tahun' => $tahun]);

                if ($res->successful() && ($res->json('ok') ?? false)) {
                    $berhasil = true;
                    $catatan[] = 'berkas ARKAS terkirim ke website';
                }
            } catch (\Throwable $e) {
                $catatan[] = 'unggah berkas tertunda ('.$e->getMessage().')';
            }
        }

        try {
            $sync = self::jalan($tahun);
            $berhasil = true;
            $catatan[] = $sync['pesan'];
        } catch (\Throwable $e) {
            if (! $berhasil) {
                $catatan[] = 'sinkron transaksi tertunda ('.$e->getMessage().')';
            }
        }

        return [
            'terkirim' => $berhasil,
            'pesan' => implode(' · ', array_filter($catatan)) ?: 'Sinkron ke website selesai',
        ];
    }
}
