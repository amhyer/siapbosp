<?php

namespace App\Console\Commands;

use App\Services\TarikArkasService;
use Illuminate\Console\Command;

/**
 * Diagnosa jalur "Tarik Data ARKAS" dari CLI.
 *
 * Padanan `php artisan siapbos:cek-aset` untuk data ARKAS: memeriksa agen
 * terbundel, database ARKAS di laptop, versi ARKAS, dan — bila diminta —
 * melakukan tarik nyata (membaca ARKAS → Excel sementara) lalu melaporkan
 * sheet yang dihasilkan tanpa mengimpor apa pun.
 */
class CekArkas extends Command
{
    protected $signature = 'siapbos:cek-arkas
        {--uji : Jalankan tarik nyata (baca ARKAS → laporkan sheet, tanpa impor)}
        {--tahun= : Tahun anggaran yang diuji (default: tahun berjalan)}';

    protected $description = 'Periksa apakah aplikasi ini bisa menarik data dari ARKAS di laptop ini';

    public function handle(): int
    {
        $tahun = (int) ($this->option('tahun') ?: now()->year);
        $uji = (bool) $this->option('uji');
        $s = TarikArkasService::statusLengkap($tahun, $uji);

        $this->line('SiapBOSP — pemeriksaan Tarik Data ARKAS');
        $this->line('  Aplikasi portabel : '.($s['portable'] ? 'ya' : 'TIDAK (tombol tarik tidak akan muncul)'));
        $this->line('  Agen node.exe     : '.($s['agen']['node'] === null ? 'TIDAK ADA' : $s['agen']['node'].' byte'));
        $this->line('  Agen tarik.cjs    : '.($s['agen']['tarik'] === null ? 'TIDAK ADA' : $s['agen']['tarik'].' byte'));
        $this->line('  Database ARKAS    : '.($s['db_ada'] ? $s['db_path'] : 'TIDAK ditemukan ('.$s['db_path'].')'));

        if (is_array($s['info'])) {
            $info = $s['info'];
            $versi = $info['versi'] ?? [];
            $this->line('  Versi agen        : '.($info['agen'] ?? 'tidak dilaporkan (kemungkinan agen lama)'));
            $this->line('  Versi ARKAS       : '.($versi['version_app'] ?? '?').' (tahun aktif '.($versi['tahun_aktif'] ?? '?').')');
            $this->line('  NPSN menurut ARKAS: '.($info['npsnFile'] ?? '?'));
        }

        $this->newLine();

        if (! $s['tersedia']) {
            $this->warn('Agen Tarik ARKAS tidak tersedia — data ARKAS TIDAK bisa ditarik dari aplikasi ini.');
            $this->line('Penyebab: bukan aplikasi portabel, atau folder agen/TarikArkas tidak ikut terpasang.');
            $this->line('Pakai paket aplikasi terbaru yang membawa agen/TarikArkas (node.exe + tarik.cjs).');

            return self::FAILURE;
        }

        if (! $s['db_ada']) {
            $this->warn('Database ARKAS belum ada di laptop ini.');
            $this->line('Buka aplikasi ARKAS sekali (unduh data sekolah), tutup lagi, lalu ulangi.');

            return self::FAILURE;
        }

        if (! $uji) {
            $this->info('Agen & database ARKAS terdeteksi. Jalankan dengan --uji untuk mencoba tarik nyata.');

            return self::SUCCESS;
        }

        $u = $s['uji'] ?? ['ok' => false, 'pesan' => 'tidak ada hasil'];
        if (! ($u['ok'] ?? false)) {
            $this->error('Tarik nyata GAGAL: '.($u['pesan'] ?? 'tanpa keterangan'));

            return self::FAILURE;
        }

        $this->info('Tarik nyata BERHASIL — NPSN '.($u['npsn'] ?? '?').', berkas '.($u['berkas_kb'] ?? 0).' KB');
        foreach (($u['sheets'] ?? []) as $nama => $jumlah) {
            $this->line('  - '.str_pad((string) $nama, 18).$jumlah.' baris');
        }

        if (! isset($u['sheets']['RAPBS_Periode'])) {
            $this->warn('Sheet RAPBS_Periode TIDAK ada — agen versi lama: transaksi masuk tanpa kode');
            $this->warn('kegiatan dan RKAS tanpa volume per tahap. Perbarui paket aplikasi.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
