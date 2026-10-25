<?php

namespace App\Console\Commands;

use App\Support\Aset;
use Illuminate\Console\Command;
use ZipArchive;

/**
 * Bangun paket pasang SiapBOSP (Tahap D).
 *
 * Isi: kode + vendor + aset build + launcher + template .env,
 *       RUNTIME PHP portable (php.exe + ext + php.ini + cacert),
 *       dan agen Tarik ARKAS (node.exe + tarik.cjs).
 * Tanpa: agen-arkas dev, node_modules, tests, dist, isi storage, .env,
 * cache dev (bootstrap/cache + public/hot), berkas sqlite, symlink.
 * Hasil: storage/app/paket/SiapBOSP-Portable-v12.zip
 */
class PaketPortable extends Command
{
    protected $signature = 'siapbos:paket-portable
        {--nama=SiapBOSP-Portable-v12.zip : Nama berkas hasil}
        {--php-src= : Folder runtime PHP yang dibundel (default: PHP yang sedang jalan)}
        {--agen-zip= : ZIP TarikArkas yang dibundel (default: storage/app/agen/TarikArkas-v1.0.0.zip)}
        {--tanpa-php : Jangan bundel runtime PHP}
        {--tanpa-agen : Jangan bundel agen Tarik ARKAS}';

    protected $description = 'Bangun ZIP pasang SiapBOSP (aplikasi + PHP + agen ARKAS) untuk laptop bendahara';

    public const VERSI = '12';

    /** Berkas ekstensi PHP yang diaktifkan (hanya bila DLL-nya ada). */
    protected const EXT_PHP = ['sqlite3', 'pdo_sqlite', 'mbstring', 'openssl', 'curl', 'zip', 'fileinfo', 'gd', 'sodium', 'exif'];

    /** DLL root yang tidak perlu dibundel (ICU/enchant/postgres — besar & tak dipakai). */
    protected const DLL_DIBUANG = ['icu', 'glib-', 'gmodule-', 'gobject-', 'libenchant', 'libpq', 'libsasl'];

    /** Ekstensi berkas agen yang dibuang (sumber/arsip, bukan runtime). */
    protected const AGEN_DIBUANG = ['c', 'h', 'cpp', 'bak', 'md', 'sh', 'ts'];

    public function handle(): int
    {
        // ---------- 0) Prasyarat: aset tampilan wajib sudah dibangun ----------
        // Laptop bendahara tidak punya Node.js, jadi CSS/JS harus ikut paket.
        // Tanpa public/build aplikasi tetap jalan tetapi memakai jalur darurat —
        // inilah penyebab halaman tampil polos pada paket yang pernah terkirim.
        $masalahAset = Aset::masalahBuild();
        if ($masalahAset !== []) {
            $this->error('Aset tampilan belum dibangun — paket akan tampil tanpa CSS.');
            foreach ($masalahAset as $masalah) {
                $this->line('  - '.$masalah);
            }
            $this->line('Jalankan dulu: npm ci && npm run build');

            return 1;
        }
        if (! is_file(base_path('public/tailwind.js'))) {
            $this->error('public/tailwind.js tidak ditemukan — cadangan mode darurat tidak akan berfungsi.');

            return 1;
        }

        $base = base_path();
        $nama = $this->option('nama');
        $out = storage_path('app/paket/'.$nama);
        @mkdir(dirname($out), 0777, true);

        $ikutSertakan = ['app', 'artisan', 'bootstrap', 'composer.json', 'composer.lock', 'config', 'database', 'launcher-windows', 'public', 'resources', 'routes', 'vendor'];
        $buangDir = ['agen-arkas', 'node_modules', 'tests', 'dist', 'storage'];
        $buangFile = ['.env', '.env.portable', 'vite.config.js', 'package.json', 'package-lock.json', '.npmrc', 'phpunit.xml', '.phpunit.result.cache', 'agen.conf', 'akun-sekolah.conf'];

        $zip = new ZipArchive;
        if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Tidak bisa menulis '.$out);

            return 1;
        }

        // ---------- 1) Aplikasi ----------
        $n = 0;
        $periksa = []; // nama entri => [berkas sumber, ukuran saat ditambahkan]
        foreach ($ikutSertakan as $rel) {
            $penuh = $base.DIRECTORY_SEPARATOR.$rel;
            if (is_file($penuh)) {
                $zip->addFile($penuh, 'SiapBOSP/'.$rel);
                $periksa['SiapBOSP/'.$rel] = [$penuh, filesize($penuh)];
                $n++;

                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($penuh, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $f) {
                $lokal = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
                $seg = explode('/', $lokal);
                if (count(array_intersect($seg, $buangDir)) > 0) {
                    continue;
                }
                // SQLite apa pun (db, -wal/-shm, cadangan .backup-*/… ) tidak ikut paket.
                if (str_contains(basename($lokal), '.sqlite') || str_ends_with($lokal, '.sqlite')) {
                    continue;
                }
                // Penanda dev/cache & symlink — kalau ikut, aset/layanan rusak di laptop bendahara.
                if ($f->isLink() || $lokal === 'public/hot' || $lokal === 'public/fonts-manifest.dev.json') {
                    continue;
                }
                if (str_starts_with($lokal, 'bootstrap/cache/')) {
                    continue;
                }
                if (in_array(basename($lokal), $buangFile, true)) {
                    continue;
                }
                if (str_ends_with($lokal, '.pdf') && str_starts_with($lokal, 'docs')) {
                    continue;
                }
                if ($f->isDir()) {
                    $zip->addEmptyDir('SiapBOSP/'.$lokal);
                } else {
                    // .bat dinormalkan ke CRLF — cmd.exe salah-parse berkas berakhiran LF saja.
                    if (str_ends_with(strtolower($lokal), '.bat')) {
                        $isi = str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($f->getPathname()));
                        $zip->addFromString('SiapBOSP/'.$lokal, str_replace("\n", "\r\n", $isi));
                    } else {
                        $zip->addFile($f->getPathname(), 'SiapBOSP/'.$lokal);
                        $periksa['SiapBOSP/'.$lokal] = [$f->getPathname(), $f->getSize()];
                    }
                    $n++;
                }
            }
        }

        // Struktur storage kosong + template env + pemasang di root
        foreach (['storage/app', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'database', 'temp'] as $d) {
            $zip->addEmptyDir('SiapBOSP/'.$d);
        }
        $zip->addFromString('SiapBOSP/.env.portable', $this->envPortable());
        $zip->addFromString('SiapBOSP/storage/app/.gitignore', "*\n!.gitignore\n");
        $zip->addFromString('SiapBOSP/PASANG-SIAPBOSP.bat',
            "@echo off\r\nREM Klik 2x berkas ini untuk memasang SiapBOSP di laptop ini.\r\ncall \"%~dp0launcher-windows\\Pasang-SiapBOSP.bat\"\r\n");

        // ---------- 2) Runtime PHP portable ----------
        $phpInfo = 'tidak dibundel (--tanpa-php)';
        if (! $this->option('tanpa-php')) {
            $phpSrc = rtrim((string) ($this->option('php-src') ?: dirname(PHP_BINARY)), '\\/');
            if (! is_file($phpSrc.DIRECTORY_SEPARATOR.'php.exe')) {
                $this->error('Runtime PHP tidak ditemukan di '.$phpSrc.' — pakai --php-src=... atau --tanpa-php.');

                return 1;
            }
            $np = 0;
            foreach (scandir($phpSrc) as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $penuh = $phpSrc.DIRECTORY_SEPARATOR.$f;
                if (! is_file($penuh)) {
                    continue;
                }
                $low = strtolower($f);
                $ext = pathinfo($low, PATHINFO_EXTENSION);
                $buang = false;
                foreach (self::DLL_DIBUANG as $awalan) {
                    if (str_starts_with($low, $awalan)) {
                        $buang = true;
                        break;
                    }
                }
                $pakai = ($low === 'php.exe') || $low === 'license.txt' || ($ext === 'dll' && ! $buang);
                if (! $pakai) {
                    continue;
                }
                $zip->addFile($penuh, 'SiapBOSP/php/'.$f);
                $periksa['SiapBOSP/php/'.$f] = [$penuh, filesize($penuh)];
                $np++;
            }
            // Redistributable C++ (kalau php.exe butuh dan belum ada di folder PHP)
            foreach (['vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll'] as $dll) {
                $sys = 'C:\\Windows\\System32\\'.$dll;
                if (! is_file($phpSrc.DIRECTORY_SEPARATOR.$dll) && is_file($sys)) {
                    $zip->addFile($sys, 'SiapBOSP/php/'.$dll);
                    $periksa['SiapBOSP/php/'.$dll] = [$sys, filesize($sys)];
                    $np++;
                }
            }
            // Ekstensi yang diperlukan aplikasi (hanya yang file DLL-nya ada)
            foreach (self::EXT_PHP as $e) {
                $dll = $phpSrc.DIRECTORY_SEPARATOR.'ext'.DIRECTORY_SEPARATOR.'php_'.$e.'.dll';
                if (is_file($dll)) {
                    $zip->addFile($dll, 'SiapBOSP/php/ext/php_'.$e.'.dll');
                    $periksa['SiapBOSP/php/ext/php_'.$e.'.dll'] = [$dll, filesize($dll)];
                    $np++;
                }
            }
            $zip->addFromString('SiapBOSP/php/php.ini', $this->isiPhpIni($phpSrc));
            // CA bundle untuk HTTPS (Sinkron ke Pusat)
            $ca = resource_path('php-portable/cacert.pem');
            if (is_file($ca)) {
                $zip->addFile($ca, 'SiapBOSP/php/cacert.pem');
                $periksa['SiapBOSP/php/cacert.pem'] = [$ca, filesize($ca)];
                $np++;
            }
            $phpInfo = $np.' berkas';
        }

        // ---------- 3) Agen Tarik ARKAS ----------
        // PENTING: ZipArchive menyalin isi berkas sumber saat close(), bukan
        // saat addFile() — folder temp ekstraksi agen HARUS tetap ada sampai
        // $zip->close(), baru boleh dihapus sesudahnya.
        $agenInfo = 'tidak dibundel (--tanpa-agen)';
        $tmpAgen = null;
        if (! $this->option('tanpa-agen')) {
            $agenZip = $this->option('agen-zip') ?: storage_path('app/agen/TarikArkas-v1.0.0.zip');
            if (is_file($agenZip)) {
                $tmp = storage_path('app/paket/tmp-agen-'.uniqid());
                $tmpAgen = $tmp;
                @mkdir($tmp, 0777, true);
                $za = new ZipArchive;
                if ($za->open($agenZip) === true && $za->extractTo($tmp)) {
                    $za->close();
                    $na = 0;
                    $it = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($it as $f) {
                        $lokal = str_replace('\\', '/', substr($f->getPathname(), strlen($tmp) + 1));
                        if (in_array(strtolower(pathinfo($lokal, PATHINFO_EXTENSION)), self::AGEN_DIBUANG, true)) {
                            continue;
                        }
                        if ($f->isDir()) {
                            $zip->addEmptyDir('SiapBOSP/agen/'.$lokal);
                        } else {
                            $zip->addFile($f->getPathname(), 'SiapBOSP/agen/'.$lokal);
                            $periksa['SiapBOSP/agen/'.$lokal] = [$f->getPathname(), $f->getSize()];
                            $na++;
                        }
                    }
                    $agenInfo = $na.' berkas';
                } else {
                    $this->warn('ZIP agen tidak bisa dibaca: '.$agenZip);
                }
            } else {
                $this->warn('ZIP agen tidak ditemukan ('.$agenZip.') — agen tidak dibundel.');
            }
        }

        $zip->close();
        if ($tmpAgen) {
            $this->hapusDir($tmpAgen);
        }

        // ---------- 4) Pemeriksaan mandiri ----------
        // addFile() baru membaca isi berkas saat close(); bila ada berkas yang
        // berubah/tersunting bersamaan, isi paket bisa tidak utuh. Bandingkan
        // ukuran setiap entri dengan ukuran saat berkas ditambahkan.
        $zg = new ZipArchive;
        if ($zg->open($out) === true) {
            $tidakUtuh = [];
            foreach ($periksa as $entri => $info) {
                $idx = $zg->locateName($entri);
                if ($idx === false) {
                    $tidakUtuh[] = $entri.' (tidak ada di ZIP)';

                    continue;
                }
                $uk = (int) $zg->statIndex($idx)['size'];
                if ($uk !== (int) $info[1]) {
                    $tidakUtuh[] = $entri.' (paket '.$uk.' B, sumber '.$info[1].' B)';
                }
            }
            $zg->close();

            if ($tidakUtuh !== []) {
                $this->error('GAGAL PERIKSA: '.count($tidakUtuh).' berkas berubah saat pengepakan (sepertinya sedang disunting). ZIP dihapus — jalankan ulang perintah ini.');
                foreach (array_slice($tidakUtuh, 0, 15) as $r) {
                    $this->line('  - '.$r);
                }
                if (count($tidakUtuh) > 15) {
                    $this->line('  ... dan '.(count($tidakUtuh) - 15).' berkas lain');
                }
                @unlink($out);

                return 1;
            }
        }

        $mb = round(filesize($out) / 1048576, 1);
        $this->info("OK: {$nama} ({$n} berkas aplikasi, {$mb} MB)");
        $this->line('   PHP bundel : '.$phpInfo);
        $this->line('   Agen ARKAS : '.$agenInfo);

        return 0;
    }

    /** php.ini minimal untuk runtime bundel. */
    protected function isiPhpIni(string $phpSrc): string
    {
        $lines = [
            '; SiapBOSP portable PHP — dibuat otomatis oleh siapbos:paket-portable.',
            '; Jangan diedit manual; ubah lewat perintah pembangun paket.',
            '[PHP]',
            'extension_dir = "ext"',
        ];
        foreach (self::EXT_PHP as $e) {
            if (is_file($phpSrc.DIRECTORY_SEPARATOR.'ext'.DIRECTORY_SEPARATOR.'php_'.$e.'.dll')) {
                $lines[] = 'extension='.$e;
            }
        }

        return implode("\r\n", array_merge($lines, [
            'memory_limit = 512M',
            'upload_max_filesize = 25M',
            'post_max_size = 32M',
            'max_execution_time = 600',
            'max_input_vars = 5000',
            '; Wajib ada "E": Laravel ServeCommand menurunkan $_ENV ke proses server;',
            '; tanpa E, proses server (dan agen yang dijalankannya) kehilangan env.',
            'variables_order = "EGPCS"',
            'date.timezone = Asia/Makassar',
            'default_charset = "UTF-8"',
            '; Balasan HTTP selalu bersih (JSON) — galat tetap tercatat di berkas log.',
            'display_errors = Off',
            'log_errors = On',
            '; Path temp & log absolut ditulis pemasang (siapbos:pasang-lokal) karena',
            '; php.exe dijalankan dengan CWD berbeda oleh `artisan serve`.',
            '; Cadangan CA saat folder kerja = folder aplikasi (Guzzle juga diatur di AppServiceProvider)',
            'curl.cainfo = "php/cacert.pem"',
            '',
        ]));
    }

    protected function envPortable(): string
    {
        return <<<'ENV'
APP_NAME=SiapBOSP
APP_ENV=local
APP_KEY=
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Makassar
LOG_CHANNEL=single
LOG_LEVEL=debug
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
ENV;
    }

    /** Hapus folder rekursif (untuk folder sementara ekstraksi agen). */
    protected function hapusDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
