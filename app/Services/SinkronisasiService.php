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
    /** Konfigurasi akun sekolah (pusat). */
    public static function conf(): array
    {
        return KonfigLokal::baca(KonfigLokal::absolut('launcher-windows/akun-sekolah.conf'));
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
    public static function jalan(): array
    {
        $conf = self::conf();
        if (empty($conf['PUSAT_URL']) || empty($conf['TOKEN'])) {
            throw new \RuntimeException('Identitas pusat belum ada (akun-sekolah.conf). Unduh "Identitas Sekolah" dari website pusat.');
        }
        $tahun = (int) session('tahun', now()->year);
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
}
