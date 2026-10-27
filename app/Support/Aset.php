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

    /** Batas waktu permintaan HTTP bukti Vite (detik). */
    private const TIMEOUT_VITE = 0.4;

    /** Penanda yang hanya dikirim server Vite pada `/@vite/client`. */
    private const PENANDA_VITE = 'vite';

    /** Direktori publik yang dipakai; null = public_path(). */
    private static ?string $dir = null;

    /** Hasil deteksi per permintaan (hindari probe berulang). */
    private static ?string $sumber = null;

    /** Hasil pemeriksaan "yang menjawab benar Vite?" per permintaan. */
    private static ?bool $viteTerbukti = null;

    /** Penguji koneksi alternatif (dipakai tes agar tidak bergantung soket nyata). */
    private static ?\Closure $penguji = null;

    /** Penguji HTTP alternatif: fn(string $url): ?bool (true/false/null = tak bisa diperiksa). */
    private static ?\Closure $pengujiHttp = null;

    /** Override direktori (dipakai tes; null mengembalikan ke public_path()). */
    public static function pakaiDirektori(?string $dir): void
    {
        self::$dir = $dir;
        self::$sumber = null;
        self::$viteTerbukti = null;
    }

    /** Ganti penguji koneksi dev server; null mengembalikan penguji TCP bawaan. */
    public static function pakaiPenguji(?callable $penguji): void
    {
        self::$penguji = $penguji === null ? null : \Closure::fromCallable($penguji);
        self::$sumber = null;
        self::$viteTerbukti = null;
    }

    /**
     * Ganti pemeriksa HTTP "apakah ini benar Vite" (dipakai tes).
     *
     * Callback menerima URL dev server dan mengembalikan true (Vite), false
     * (ada yang menjawab tetapi bukan Vite), atau null (tidak bisa diperiksa).
     */
    public static function pakaiPengujiHttp(?callable $penguji): void
    {
        self::$pengujiHttp = $penguji === null ? null : \Closure::fromCallable($penguji);
        self::$sumber = null;
        self::$viteTerbukti = null;
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

    /**
     * Apakah server Vite di alamat `hot` benar-benar bisa dipakai.
     *
     * Dua tahap, karena port terbuka saja TIDAK cukup:
     *   1. koneksi TCP (murah, menyaring server yang sudah mati);
     *   2. bukti HTTP: `GET {hot}/@vite/client` harus menjawab dengan penanda
     *      Vite.
     *
     * Tahap 2 menutup kasus yang dulu tetap membuat halaman polos: port 5173
     * dipakai proses LAIN (sisa `npm run dev` proyek lain, atau aplikasi lain).
     * Koneksi TCP berhasil, jadi `public/hot` dianggap sah dan `@vite`
     * mengeluarkan `http://localhost:5173/resources/css/app.css` — yang
     * dijawab 404 oleh proses itu. Hasilnya halaman tanpa CSS sama sekali.
     */
    public static function hotHidup(): bool
    {
        $url = self::hotUrl();
        if ($url === null) {
            return false;
        }

        $tcp = self::$penguji !== null
            ? (bool) (self::$penguji)($url)
            : self::probeTcp($url);

        if (! $tcp) {
            return false;
        }

        // null = tidak bisa diperiksa (mis. allow_url_fopen mati): percayai TCP
        // agar lingkungan yang sah tidak ikut kehilangan dev server.
        return self::viteMenjawab($url) ?? true;
    }

    /**
     * Bukti bahwa yang menjawab di alamat dev server benar-benar Vite.
     *
     * true  = Vite menjawab (`/@vite/client` memuat penanda Vite)
     * false = ada yang menjawab, tetapi bukan Vite
     * null  = tidak bisa diperiksa di lingkungan ini
     */
    public static function viteMenjawab(string $url): ?bool
    {
        if (self::$viteTerbukti !== null) {
            return self::$viteTerbukti;
        }

        if (self::$pengujiHttp !== null) {
            return self::$viteTerbukti = (self::$pengujiHttp)($url);
        }

        $terbuka = ini_get('allow_url_fopen');
        if (in_array(strtolower((string) $terbuka), ['', '0', 'off', 'false', 'no'], true)) {
            return null;
        }

        $konteks = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => self::TIMEOUT_VITE,
            'ignore_errors' => true, // tetap baca isi balasan 404 milik proses lain
            'header' => "Connection: close\r\n",
        ]]);

        $isi = @file_get_contents(rtrim($url, '/').'/@vite/client', false, $konteks);
        if ($isi === false) {
            // TCP menjawab tetapi HTTP gagal: bukan server HTTP yang sehat.
            return self::$viteTerbukti = false;
        }

        return self::$viteTerbukti = str_contains(mb_strtolower($isi), self::PENANDA_VITE);
    }

    /** Uji koneksi TCP singkat ke alamat dev server. */
    protected static function probeTcp(string $url): bool
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

    /**
     * Alamat dev server yang AMAN dipakai di CSP dan oleh `@vite`.
     *
     * Literal IPv6 ber-kurung (`http://[::1]:5173`) BUKAN host-source yang sah
     * menurut grammar CSP: `host-part` tidak mengenal kurung siku maupun titik
     * dua, sehingga Chromium membuangnya saat mem-parse
     * ("contains an invalid source … It will be ignored"). Akibatnya
     * `script-src`/`style-src` yang memuatnya TETAP memblokir aset dev server —
     * mengizinkan `[::1]` di header tidak berefek apa pun.
     *
     * Jalan keluarnya ejaan `localhost`: OS menerjemahkannya ke ::1, dan
     * sumber itu sah bagi peramban.
     */
    public static function hotUrlNormal(): ?string
    {
        $url = self::hotUrl();
        if ($url === null) {
            return null;
        }

        $bagian = parse_url($url);
        $host = strtolower(trim((string) ($bagian['host'] ?? ''), '[]'));

        // Hanya host loopback/wildcard yang perlu dieja ulang.
        if (! in_array($host, ['::1', '::', '0.0.0.0'], true)) {
            return $url;
        }

        $skema = strtolower((string) ($bagian['scheme'] ?? 'http'));
        $port = $bagian['port'] ?? ($skema === 'https' ? 443 : 80);

        return $skema.'://localhost:'.$port;
    }

    /**
     * Arahkan `@vite` ke alamat yang sudah dinormalkan.
     *
     * Laravel membaca `public/hot` sendiri, jadi normalisasi di `Aset` saja
     * tidak mengubah apa yang dicetak `@vite`. Berkas hot sementara ditulis di
     * `storage/framework` (bukan public) lalu `Vite::useHotFile()` diarahkan ke
     * situ — `public/hot` milik Vite tidak diubah, jadi `npm run dev` tetap
     * berjalan seperti biasa.
     *
     * @return bool true bila pengalihan dipasang
     */
    public static function arahkanVite(): bool
    {
        $asli = self::hotUrl();
        $normal = self::hotUrlNormal();

        if ($asli === null || $normal === null || $asli === $normal) {
            return false;
        }

        $berkas = storage_path('framework'.DIRECTORY_SEPARATOR.'vite-hot');

        try {
            $isi = is_file($berkas) ? trim((string) @file_get_contents($berkas)) : '';
            if ($isi !== $normal) {
                @file_put_contents($berkas, $normal);
            }
            if (trim((string) @file_get_contents($berkas)) !== $normal) {
                return false; // tidak bisa menulis: biarkan @vite apa adanya
            }

            \Illuminate\Support\Facades\Vite::useHotFile($berkas);
        } catch (\Throwable $e) {
            Log::warning('Aset: gagal mengarahkan @vite ke '.$normal.' — '.$e->getMessage());

            return false;
        }

        Log::warning(
            'Aset: public/hot memakai host yang tidak sah untuk CSP ('.$asli
            .') — literal IPv6 ber-kurung diabaikan peramban. @vite diarahkan ke '.$normal.'.'
        );

        return true;
    }

    /** Alamat asal dev server (untuk CSP) atau null bila tidak aktif. */
    public static function hotAsal(): ?string
    {
        if (! self::hotHidup()) {
            return null;
        }

        $bagian = parse_url((string) self::hotUrlNormal());
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
            'hot_url_normal' => self::hotUrlNormal(),
            'hot_hidup' => $url !== null && self::hotHidup(),
            'vite_terbukti' => $url === null || ! self::hotHidup() ? null : self::viteMenjawab($url),
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
