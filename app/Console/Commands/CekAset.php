<?php

namespace App\Console\Commands;

use App\Support\Aset;
use Illuminate\Console\Command;

/**
 * Diagnosa pemuatan CSS/JS halaman (audit tampilan).
 *
 * Dipakai saat aplikasi tampil "polos" tanpa gaya: perintah ini menyebutkan
 * jalur aset mana yang sedang dipakai (build / dev server / darurat) beserta
 * berkas yang hilang, tanpa perlu membuka mode debug.
 */
class CekAset extends Command
{
    protected $signature = 'siapbos:cek-aset';

    protected $description = 'Periksa sumber aset CSS/JS halaman (build, dev server, atau mode darurat)';

    public function handle(): int
    {
        $laporan = Aset::periksa();

        $this->line('SiapBOSP — pemeriksaan aset tampilan');
        $this->line('  Lingkungan      : '.config('app.env').' (APP_DEBUG='.(config('app.debug') ? 'true' : 'false').')');
        $this->line('  Direktori publik: '.$laporan['direktori']);
        $this->newLine();

        // --- dev server (npm run dev) ---
        if ($laporan['hot_berkas']) {
            $this->line('  public/hot      : ada ('.($laporan['hot_url'] ?? 'tanpa URL').')');
            $this->line('  Server Vite     : '.($laporan['hot_hidup'] ? 'hidup — aset diambil dari dev server' : 'TIDAK menjawab — berkas hot akan dihapus otomatis'));
        } else {
            $this->line('  public/hot      : tidak ada (bagus untuk produksi)');
        }

        // --- hasil build ---
        $this->line('  manifest build  : '.($laporan['manifest_ada'] ? $laporan['manifest'] : 'tidak ada'));
        if ($laporan['manifest_ada']) {
            if ($laporan['build_lengkap']) {
                $this->line('  Kelengkapan     : lengkap (semua berkas rujukan manifest ada)');
            } else {
                $this->line('  Kelengkapan     : TIDAK lengkap');
                foreach ($laporan['masalah_build'] as $masalah) {
                    $this->line('                    - '.$masalah);
                }
            }
        }
        $this->line('  public/tailwind.js: '.($laporan['tailwind_js'] ? 'ada (cadangan mode darurat)' : 'TIDAK ADA — mode darurat akan tampil tanpa utility'));
        $this->newLine();

        $sumber = $laporan['sumber'];
        $nama = [
            Aset::HOT => 'dev server Vite (public/hot)',
            Aset::BUILD => 'aset hasil build (public/build)',
            Aset::DARURAT => 'mode darurat (/tailwind.js + gaya bawaan layout)',
        ][$sumber];

        $this->info('Sumber CSS yang dipakai halaman: '.$nama);
        $this->newLine();

        if ($sumber === Aset::BUILD || $sumber === Aset::HOT) {
            $this->line('Tampilan seharusnya normal. Bila masih polos di browser:');
            $this->line('  1) muat ulang paksa (Ctrl+F5 / Shift+Reload);');
            $this->line('  2) buka DevTools → Console + Network, cari status 404 atau pesan CSP.');

            return self::SUCCESS;
        }

        $this->warn('Aset build belum siap — aplikasi memakai jalur darurat.');
        $this->line('Perbaiki dengan salah satu cara:');
        $this->line('  A. Pemakaian normal (disarankan):');
        $this->line('       npm ci && npm run build');
        $this->line('     lalu pastikan public/build/manifest.json ada.');
        $this->line('  B. Sedang mengembangkan tampilan:');
        $this->line('       npm run dev     (biarkan terminal ini terbuka)');
        $this->line('  C. Setelah salah satu di atas:');
        $this->line('       php artisan optimize:clear');
        $this->line('     lalu muat ulang halaman dengan Ctrl+F5.');

        return self::FAILURE;
    }
}
