<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Penentu sumber CSS/JS untuk seluruh halaman web.
 *
 * Ada tiga jalur pemuatan aset (lihat docs/AUDIT-CSS-HALAMAN.md):
 *
 *  1. `hot`    — server Vite dev (`npm run dev`) sedang hidup; `@vite` menunjuk
 *                ke alamat dev server. Alamat itu beda port sehingga BUKAN
 *                'self' bagi CSP — middleware KeamananHeader wajib mengizinkannya.
 *  2. `build`  — hasil `npm run build` ada dan lengkap di `public/build`.
 *  3. `darurat`— tidak keduanya: halaman memakai `/tailwind.js` + gaya
 *                komponen di `layouts/_gaya-darurat.blade.php`.
 *
 * Dua bug nyata yang ditutup kelas ini (keduanya membuat halaman tampil polos
 * tanpa CSS sama sekali):
 *
 *  - `public/hot` yang BASI (sisa `npm run dev` yang sudah mati). Dulu berkas
 *    itu membuat `@vite` mengeluarkan alamat `http://localhost:5173/...` yang
 *    tidak ada isinya → CSS 404. Sekarang berkas hot diperiksa dengan koneksi
 *    TCP singkat; bila server dev tidak menjawab, berkas hot dihapus dan
 *    aplikasi memakai aset build/darurat.
 *  - `manifest.json` ada tetapi berkas aset yang dirujuknya hilang (build
 *    terpotong, ZIP dipasang tak lengkap). Dulu `@vite` tetap dipakai sehingga
 *    CSS 404. Sekarang setiap berkas yang dirujuk manifest diverifikasi ada;
 *    bila tidak, aplikasi beralih ke jalur darurat.
 */
final class Aset
{
    public const HOT = 'hot';

    public const BUILD = 'build';

    public const DARURAT = 'darurat';

    /** Batas waktu pemeriksaan server Vite (detik). */
    private const TIMEOUT_PROBE = 0.2;

    /** Direktori publik yang dipakai; null = public_path(). */
    private static ?string $dir = null;

    /** Hasil deteksi per permintaan (hindari probe berulang). */
    private static ?string $sumber = null;

    /** Penguji koneksi alternatif (dipakai tes agar tidak bergantung soket nyata). */
    private static ?\Closure $penguji = null;

    /** Override direktori (dipakai tes; null mengembalikan ke public_path()). */
    public static function pakaiDirektori(?string $dir): void
    {
        self::$dir = $dir;
        self::$sumber = null;
    }

    /** Ganti penguji koneksi dev server; null mengembalikan penguji TCP bawaan. */
    public static function pakaiPenguji(?callable $penguji): void
    {
        self::$penguji = $penguji === null ? null : \Closure::fromCallable($penguji);
        self::$sumber = null;
    }

    public static function dir(): string
    {
        return self::$dir ?? public_path();
    }

    public static function berkasHot(): string
    {
        return self::dir().DIRECTORY_SEPARATOR.'hot';
    }

    public static function berkasManifest(): string
    {
        return self::dir().DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json';
    }

    /** Isi `public/hot` tanpa garis miring akhir, mis. `http://localhost:5173`. */
    public static function hotUrl(): ?string
    {
        $berkas = self::berkasHot();
        if (! is_file($berkas)) {
            return null;
        }

        $url = trim((string) @file_get_contents($berkas));

        return $url === '' ? null : rtrim($url, '/');
    }

    /** Apakah server Vite di alamat `hot` benar-benar menerima koneksi. */
    public static function hotHidup(): bool
    {
        $url = self::hotUrl();
        if ($url === null) {
            return false;
        }

        if (self::$penguji !== null) {
            return (bool) (self::$penguji)($url);
        }

        return self::probeTcp($url);
    }

    /** Uji koneksi TCP singkat ke alamat dev server. */
    private static function probeTcp(string $url): bool
    {
        $bagian = parse_url($url);
        $host = $bagian['host'] ?? null;
        if (! is_string($host) || $host === '') {
            return false;
        }

        $skema = strtolower($bagian['scheme'] ?? 'http');
        $port = $bagian['port'] ?? ($skema === 'https' ? 443 : 80);
        $target = ($skema === 'https' ? 'ssl://' : 'tcp://').$host.':'.$port;

        $errno = 0;
        $errstr = '';
        $koneksi = @stream_socket_client($target, $errno, $errstr, self::TIMEOUT_PROBE);
        if ($koneksi === false) {
            return false;
        }
        fclose($koneksi);

        return true;
    }

    /** Alamat asal dev server (untuk CSP) atau null bila tidak aktif. */
    public static function hotAsal(): ?string
    {
        if (! self::hotHidup()) {
            return null;
        }

        $bagian = parse_url((string) self::hotUrl());
        $host = $bagian['host'] ?? 'localhost';
        $skema = strtolower($bagian['scheme'] ?? 'http');
        $port = $bagian['port'] ?? ($skema === 'https' ? 443 : 80);

        return $skema.'://'.$host.':'.$port;
    }

    /** Asal WebSocket untuk HMR (CSP connect-src) atau null. */
    public static function hotWs(): ?string
    {
        $asal = self::hotAsal();
        if ($asal === null) {
            return null;
        }

        return preg_replace('/^http/', 'ws', $asal);
    }

    /** Isi manifest Vite, atau null bila tidak ada/tidak terbaca. */
    public static function manifest(): ?array
    {
        $berkas = self::berkasManifest();
        if (! is_file($berkas)) {
            return null;
        }

        $isi = json_decode((string) @file_get_contents($berkas), true);

        return is_array($isi) ? $isi : null;
    }

    /**
     * Daftar masalah pada aset build (kosong = build lengkap dan siap dipakai).
     *
     * @return list<string>
     */
    public static function masalahBuild(): array
    {
        $manifest = self::manifest();
        if ($manifest === null) {
            return ['berkas public/build/manifest.json tidak ditemukan'];
        }

        if (! isset($manifest['resources/css/app.css'])) {
            return ['manifest tidak memuat entri resources/css/app.css'];
        }

        if (empty($manifest['resources/css/app.css']['file'])) {
            return ['entri resources/css/app.css tidak punya berkas keluaran'];
        }

        // Rujukan di manifest relatif terhadap folder build/ (mis. assets/app-x.css).
        $akarBuild = self::dir().DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR;
        $masalah = [];
        foreach (self::rujukanManifest($manifest) as $relatif) {
            $penuh = $akarBuild.str_replace('/', DIRECTORY_SEPARATOR, ltrim($relatif, '/'));
            if (! is_file($penuh)) {
                $masalah[] = 'berkas rujukan manifest hilang: '.$relatif;
            }
        }

        return $masalah;
    }

    /** @return list<string> berkas yang dirujuk manifest (relatif terhadap public/) */
    private static function rujukanManifest(array $manifest): array
    {
        $daftar = [];
        foreach ($manifest as $entri) {
            if (! is_array($entri)) {
                continue;
            }
            if (isset($entri['file']) && is_string($entri['file'])) {
                $daftar[] = $entri['file'];
            }
            foreach (['css', 'assets'] as $kunci) {
                foreach ((array) ($entri[$kunci] ?? []) as $nilai) {
                    if (is_string($nilai)) {
                        $daftar[] = $nilai;
                    }
                }
            }
        }

        return array_values(array_unique($daftar));
    }

    /**
     * Berkas CSS hasil build (terbaru lebih dulu), dibaca langsung dari disk.
     *
     * Sengaja TIDAK lewat manifest: bila manifest rusak/hilang tetapi berkas
     * CSS-nya ada, halaman tetap bisa ditata. Inilah jaring pengaman terakhir
     * sebelum mode darurat.
     *
     * @return list<string> path absolut
     */
    public static function cssBerkas(): array
    {
        return self::berkasAset('css');
    }

    /** @return list<string> path absolut */
    public static function jsBerkas(): array
    {
        return self::berkasAset('js');
    }

    /** @return list<string> */
    private static function berkasAset(string $ekstensi): array
    {
        $pola = self::dir().DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'*.'.$ekstensi;
        $berkas = glob($pola) ?: [];

        // Terbaru lebih dulu: bila ada sisa build lama, yang baru dipakai.
        usort($berkas, fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));

        return array_values(array_filter($berkas, 'is_file'));
    }

    /**
     * URL CSS siap pakai (`/build/assets/app-xxxx.css`), atau null bila tidak ada.
     *
     * Dipakai layout sebagai <link> biasa — tanpa Vite, tanpa manifest, tanpa
     * ketergantungan pada berkas `hot`. Nama berkas sudah ber-hash Vite,
     * jadi cache browser aman.
     *
     * @return list<string>
     */
    public static function cssUrl(): array
    {
        return array_map(
            fn (string $berkas): string => '/build/assets/'.basename($berkas),
            self::cssBerkas()
        );
    }

    /** @return list<string> */
    public static function jsUrl(): array
    {
        return array_map(
            fn (string $berkas): string => '/build/assets/'.basename($berkas),
            self::jsBerkas()
        );
    }

    /** True bila CSS hasil build bisa dirujuk langsung (jalur `build`). */
    public static function cssSiap(): bool
    {
        return self::cssBerkas() !== [];
    }

    public static function buildLengkap(): bool
    {
        return self::masalahBuild() === [];
    }

    /** Sumber aset yang dipakai halaman ini: hot | build | darurat. */
    public static function sumber(): string
    {
        if (self::$sumber !== null) {
            return self::$sumber;
        }

        if (self::hotUrl() !== null) {
            if (self::hotHidup()) {
                return self::$sumber = self::HOT;
            }

            self::buangHotBasi();
        }

        // Cukup berkas CSS-nya ada: layout merujuknya lewat <link> biasa,
        // sehingga manifest yang rusak tidak lagi membuat halaman polos.
        return self::$sumber = self::cssSiap() ? self::BUILD : self::DARURAT;
    }

    /**
     * True bila `@vite` perlu dipakai — yaitu hanya saat server dev hidup.
     *
     * Pada jalur build, layout sengaja memakai <link> biasa ke berkas CSS hasil
     * build: tidak butuh manifest, tidak butuh berkas `hot`, tidak bisa
     * diarahkan ke dev server yang mati.
     */
    public static function viteAktif(): bool
    {
        return self::sumber() === self::HOT;
    }

    /** `tailwind.js` (Play CDN) hanya dimuat bila berkasnya memang ada. */
    public static function tailwindJsAda(): bool
    {
        return is_file(self::dir().DIRECTORY_SEPARATOR.'tailwind.js');
    }

    /**
     * Hapus `public/hot` yang menunjuk server dev mati.
     *
     * Tanpa ini `@vite` terus mengeluarkan alamat dev server yang tidak ada
     * isinya, sehingga halaman tampil tanpa CSS sama sekali.
     */
    public static function buangHotBasi(): bool
    {
        $berkas = self::berkasHot();
        if (! is_file($berkas)) {
            return false;
        }

        $terhapus = @unlink($berkas);
        Log::warning(
            'Aset: public/hot menunjuk server Vite yang tidak menjawab — berkas hot '
            .($terhapus ? 'dihapus' : 'GAGAL dihapus (periksa izin berkas)')
            .'. Jalankan "npm run build" atau "npm run dev".'
        );

        return $terhapus;
    }

    /**
     * Laporan diagnostik untuk `php artisan siapbos:cek-aset`.
     *
     * @return array<string, string|bool|null>
     */
    public static function periksa(): array
    {
        self::$sumber = null;
        $url = self::hotUrl();

        return [
            'direktori' => self::dir(),
            'hot_berkas' => $url !== null,
            'hot_url' => $url,
            'hot_hidup' => $url !== null && self::hotHidup(),
            'manifest' => self::berkasManifest(),
            'manifest_ada' => is_file(self::berkasManifest()),
            'build_lengkap' => self::buildLengkap(),
            'masalah_build' => self::masalahBuild(),
            'tailwind_js' => self::tailwindJsAda(),
            'css' => self::cssUrl(),
            'js' => self::jsUrl(),
            'sumber' => self::sumber(),
        ];
    }
}
