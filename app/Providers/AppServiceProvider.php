<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use App\Models\RkasHeader;
use App\Models\TutupBuku;
use App\Services\Aktivitas;
use Illuminate\Support\Facades\Http;
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
