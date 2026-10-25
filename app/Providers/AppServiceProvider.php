<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use App\Models\RkasHeader;
use App\Models\TutupBuku;
use App\Services\Aktivitas;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->pastikanFolderSementara();
    }

    /**
     * Windows: proses yang dijalankan tanpa variabel TMP/TEMP (mis. server
     * `artisan serve` yang hanya meneruskan sebagian env) membuat SQLite gagal
     * menulis transaksi besar — "unable to open database file" di tengah impor
     * ARKAS. Arahkan TMP/TEMP ke folder temp milik aplikasi bila belum ada atau
     * tidak bisa ditulis. Kunci: putenv() mengubah environment proses sehingga
     * GetTempPath() milik SQLite ikut melihat folder ini.
     */
    protected function pastikanFolderSementara(): void
    {
        foreach (['TMP', 'TEMP'] as $kunci) {
            $nilai = getenv($kunci);
            if (is_string($nilai) && $nilai !== '' && is_dir($nilai) && is_writable($nilai)) {
                continue;
            }

            $dir = base_path('temp');
            if (! is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            if (is_dir($dir) && is_writable($dir)) {
                putenv($kunci.'='.$dir);
                $_ENV[$kunci] = $dir;
                $_SERVER[$kunci] = $dir;
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->pasangBundelCa();
        $this->pasangPembatasLaju();
        $this->periksaKonfigurasiProduksi();
        KasTransaction::created(function ($t) {
            Aktivitas::catat('transaksi',
                $t->labelType().' '.rp($t->total).' — '.Str::limit($t->keterangan, 80), $t->school_id);
        });
        RkasHeader::updated(function ($r) {
            if ($r->wasChanged('status')) {
                Aktivitas::catat('rkas', 'RKAS tahun '.$r->tahun.' → status: '.$r->status, $r->school_id);
            }
        });
        TutupBuku::created(fn ($t) => Aktivitas::catat(
            'tutup_buku', 'Buku '.($t->bulan ? 'BKU bulan '.$t->bulan : ($t->stage?->nama ?? 'tahap')).' dikunci', $t->school_id));
        TutupBuku::deleted(fn ($t) => Aktivitas::catat(
            'buka_buku', 'Buku '.($t->bulan ? 'BKU bulan '.$t->bulan : ($t->stage?->nama ?? 'tahap')).' dibuka kembali', $t->school_id));
        BkuTutupBulan::created(fn ($t) => Aktivitas::catat(
            'tutup_buku', 'BKU bulan '.$t->bulan.' dikunci', $t->school_id));
        BkuTutupBulan::deleted(fn ($t) => Aktivitas::catat(
            'buka_buku', 'BKU bulan '.$t->bulan.' dibuka kembali', $t->school_id));
        Announcement::created(fn ($a) => Aktivitas::catat(
            'pengumuman', 'Pengumuman terbit: '.$a->judul, null));
    }

    /**
     * Peringatan konfigurasi berbahaya di produksi (R-06).
     *
     * Tidak menghentikan aplikasi (agar pemasangan tidak mendadak mati), tetapi
     * meninggalkan jejak yang mudah dilihat pada log server.
     */
    protected function periksaKonfigurasiProduksi(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (config('app.debug')) {
            Log::warning('KEAMANAN: APP_DEBUG=true di produksi — pesan galat dapat membocorkan kueri SQL, '
                .'path berkas, dan nilai .env. Set APP_DEBUG=false.');
        }

        if (str_starts_with((string) config('app.url'), 'https://') && ! config('session.secure')) {
            Log::warning('KEAMANAN: APP_URL sudah https tetapi SESSION_SECURE_COOKIE belum true — cookie sesi '
                .'dapat terkirim lewat koneksi tidak terenkripsi.');
        }
    }

    /**
     * Pembatas laju untuk endpoint berat (R-04) dan email massal (R-05).
     *
     * Parsing XLSX ARKAS sampai 20–40 MB memakan CPU & memori; tanpa batas,
     * satu akun (atau token yang bocor) dapat menahan server. Kuncinya memakai
     * id pengguna bila ada, jika tidak IP.
     */
    protected function pasangPembatasLaju(): void
    {
        $kunci = fn (Request $r) => (string) ($r->user()?->id ?: $r->ip());

        RateLimiter::for('unggah', fn (Request $r) => Limit::perMinutes(10, 10)->by('unggah|'.$kunci($r)));
        RateLimiter::for('unggah-api', fn (Request $r) => Limit::perMinutes(10, 6)->by('unggah-api|'.$kunci($r)));
        RateLimiter::for('pengumuman', fn (Request $r) => Limit::perHour(5)->by('pengumuman|'.$kunci($r)));
    }

    /**
     * PHP Windows (curl) butuh CA bundle untuk HTTPS. Kita bundel
     * resources/php-portable/cacert.pem supaya Sinkron ke Pusat (HTTPS)
     * jalan tanpa mengubah php.ini mesin.
     */
    protected function pasangBundelCa(): void
    {
        foreach ([resource_path('php-portable/cacert.pem'), base_path('php/cacert.pem')] as $ca) {
            if (is_file($ca)) {
                Http::globalOptions(['verify' => $ca]);

                return;
            }
        }
    }
}
