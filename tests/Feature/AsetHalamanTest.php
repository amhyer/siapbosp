<?php

namespace Tests\Feature;

use App\Http\Middleware\KeamananHeader;
use App\Support\Aset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Regresi "halaman tampil polos tanpa CSS".
 *
 * Tiga jalur aset (dev server Vite / hasil build / darurat) diuji pada
 * direktori publik sementara supaya tidak mengganggu `public/` yang sedang
 * dipakai tes lain maupun hasil build di CI.
 */
class AsetHalamanTest extends TestCase
{
    /** @var list<string> direktori publik sementara yang harus dibersihkan */
    private array $tmp = [];

    protected function tearDown(): void
    {
        Aset::pakaiDirektori(null);
        Aset::pakaiPenguji(null);
        Aset::pakaiPengujiHttp(null);
        // arahkanVite() memindahkan hot file Laravel Vite; kembalikan ke bawaan
        // agar tes lain tidak mewarisi alamat dev server dari tes ini.
        \Illuminate\Support\Facades\Vite::useHotFile(public_path('hot'));
        foreach ($this->tmp as $dir) {
            $this->hapusDir($dir);
        }
        $this->tmp = [];
        parent::tearDown();
    }

    private function dirPublik(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aset-uji-'.uniqid();
        mkdir($dir, 0777, true);
        $this->tmp[] = $dir;
        Aset::pakaiDirektori($dir);

        return $dir;
    }

    private function hapusDir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * Ganti penguji koneksi dev server supaya tes tidak bergantung pada soket
     * nyata (port bisa saja dipakai proses lain di mesin CI).
     */
    private function anggapServerDev(bool $hidup, ?bool $vite = null): void
    {
        Aset::pakaiPenguji(fn (string $url): bool => $hidup);
        // Sejak ada tahap kedua, "port terbuka" saja tidak cukup: pemeriksaan
        // HTTP juga harus dijawab. Bawaannya dianggap Vite yang sah.
        Aset::pakaiPengujiHttp(fn (string $url): ?bool => $vite ?? $hidup);
    }

    /** Buat manifest tiruan; $berkasAda = berkas keluaran yang benar-benar ditulis. */
    private function buatBuild(string $dir, bool $berkasAda = true): void
    {
        $aset = $dir.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'assets';
        mkdir($aset, 0777, true);
        if ($berkasAda) {
            file_put_contents($aset.DIRECTORY_SEPARATOR.'app.css', '.inp{color:red}');
            file_put_contents($aset.DIRECTORY_SEPARATOR.'app.js', 'console.log(1)');
        }
        file_put_contents($dir.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/app.css', 'src' => 'resources/css/app.css'],
            'resources/js/app.js' => ['file' => 'assets/app.js', 'src' => 'resources/js/app.js'],
        ], JSON_UNESCAPED_SLASHES));
    }

    private function csp(): string
    {
        $tanggapan = (new KeamananHeader())->handle(
            Request::create('/login'),
            fn (): Response => new Response('ok')
        );

        return (string) $tanggapan->headers->get('Content-Security-Policy');
    }

    public function test_hot_basi_dihapus_dan_halaman_memakai_jalur_darurat(): void
    {
        $dir = $this->dirPublik();
        $this->anggapServerDev(false);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://127.0.0.1:5173');

        $this->assertSame(Aset::DARURAT, Aset::sumber(),
            'public/hot yang menunjuk server mati harus diabaikan, bukan dipakai @vite.');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'hot',
            'Berkas hot basi harus dihapus supaya @vite tidak mengeluarkan alamat dev server yang mati.');
    }

    public function test_hot_hidup_dipakai_dan_originnya_diizinkan_csp(): void
    {
        $dir = $this->dirPublik();
        $this->anggapServerDev(true);
        $port = 5199;
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://127.0.0.1:'.$port);

        $this->assertSame(Aset::HOT, Aset::sumber());

        $csp = $this->csp();
        $this->assertStringContainsString('http://127.0.0.1:'.$port, $csp,
            'CSP harus mengizinkan aset dev server; tanpa ini CSS dev diblokir dan halaman tampil polos.');
        $this->assertStringContainsString('ws://127.0.0.1:'.$port, $csp,
            'connect-src harus mengizinkan WebSocket HMR.');
        $this->assertStringContainsString("'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $this->direktif($csp, 'script-src'));
    }

    /**
     * Laporan lapangan: CSP sudah memuat `http://[::1]:5173`, tetapi peramban
     * TETAP memblokir aset dev server. Sebabnya literal IPv6 ber-kurung bukan
     * host-source yang sah menurut grammar CSP — Chromium membuangnya saat
     * mem-parse ("contains an invalid source … It will be ignored"), sehingga
     * `script-src`/`style-src` tidak pernah cocok dengan http://[::1]:5173.
     */
    public function test_hot_beralamat_ipv6_dinormalkan_ke_localhost(): void
    {
        $dir = $this->dirPublik();
        $this->buatBuild($dir);
        $this->anggapServerDev(true, true);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://[::1]:5173');

        $this->assertSame('http://localhost:5173', Aset::hotUrlNormal(),
            'Literal IPv6 harus dieja ulang menjadi localhost agar sah bagi CSP.');
        $this->assertSame('http://localhost:5173', Aset::hotAsal());

        $csp = $this->csp();
        $this->assertStringContainsString('http://localhost:5173', $csp);
        $this->assertStringNotContainsString('[::1]', $csp,
            'Literal IPv6 di CSP diabaikan peramban — jangan dicetak sama sekali.');
        $this->assertStringContainsString('ws://localhost:5173', $csp,
            'connect-src butuh ejaan yang sama untuk WebSocket HMR.');
    }

    /** Alamat yang sudah hostname biasa tidak boleh diubah. */
    public function test_hot_beralamat_localhost_tidak_diubah(): void
    {
        $dir = $this->dirPublik();
        $this->anggapServerDev(true, true);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://127.0.0.1:5173');

        $this->assertSame('http://127.0.0.1:5173', Aset::hotUrlNormal());
        $this->assertStringNotContainsString('localhost', (string) Aset::hotUrlNormal());
    }

    /**
     * `@vite` membaca `public/hot` sendiri; normalisasi di Aset saja tidak
     * mengubah alamat yang dicetak. `arahkanVite()` menulis hot file sementara
     * dan mengarahkan Vite ke situ, sehingga HTML memakai localhost.
     */
    public function test_vite_mencetak_alamat_localhost_bukan_ipv6(): void
    {
        $dir = $this->dirPublik();
        $this->anggapServerDev(true, true);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://[::1]:5173');

        $this->assertTrue(Aset::arahkanVite(), 'Pengalihan @vite seharusnya terpasang.');

        $html = (string) $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('http://localhost:5173/@vite/client', $html);
        $this->assertStringNotContainsString('[::1]', $html,
            'HTML tidak boleh memuat literal IPv6 yang akan diblokir CSP.');
    }

    /**
     * Port dev server terbuka tetapi yang menjawab BUKAN Vite (mis. sisa
     * `npm run dev` proyek lain yang menahan port 5173). Dulu `Aset` hanya
     * menguji koneksi TCP, sehingga `@vite` tetap dipakai dan CSS-nya 404 —
     * halaman tampil polos TOTAL tanpa penjelasan.
     */
    public function test_port_dev_dipakai_aplikasi_lain_bukan_vite(): void
    {
        $dir = $this->dirPublik();
        $this->buatBuild($dir);
        // TCP menjawab, tetapi /@vite/client tidak memuat penanda Vite.
        $this->anggapServerDev(true, false);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://127.0.0.1:5173');

        $this->assertSame(Aset::BUILD, Aset::sumber(),
            'Port yang menjawab tetapi bukan Vite harus diabaikan; pakai aset build.');
        $this->assertFalse(Aset::viteAktif(), '@vite tidak boleh dipakai bila Vite tidak terbukti.');
        $this->assertFileDoesNotExist($dir.DIRECTORY_SEPARATOR.'hot',
            'Berkas hot yang menunjuk proses asing harus dibuang agar tidak dipakai lagi.');
        $this->assertSame(false, Aset::viteMenjawab('http://127.0.0.1:5173'));

        // CSP juga tidak boleh membuka origin asing itu.
        $this->assertStringNotContainsString('127.0.0.1:5173', $this->csp(),
            'Origin proses asing tidak boleh dimasukkan ke CSP.');
    }

    /**
     * Lingkungan tanpa `allow_url_fopen` tidak bisa membuktikan Vite. Jangan
     * sampai dev server yang sah ikut dimatikan hanya karena pemeriksaan
     * tambahan tidak bisa dijalankan.
     */
    public function test_bila_vite_tak_bisa_diperiksa_dev_server_tetap_dipakai(): void
    {
        $dir = $this->dirPublik();
        $this->buatBuild($dir);
        $this->anggapServerDev(true, null);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://127.0.0.1:5173');

        $this->assertSame(Aset::HOT, Aset::sumber(),
            'Pemeriksaan yang tidak bisa dilakukan tidak boleh mematikan dev server.');
        $this->assertTrue(Aset::viteAktif());
    }

    public function test_tanpa_hot_csp_tidak_membuka_origin_asing(): void
    {
        $this->dirPublik();

        $csp = $this->csp();
        $this->assertStringNotContainsString('127.0.0.1:', $csp);
        $this->assertStringNotContainsString('localhost:', $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
    }

    public function test_build_lengkap_dipakai_bila_tidak_ada_hot(): void
    {
        $dir = $this->dirPublik();
        $this->buatBuild($dir);

        $this->assertSame([], Aset::masalahBuild());
        $this->assertSame(Aset::BUILD, Aset::sumber());
    }

    public function test_manifest_tanpa_berkas_dianggap_belum_lengkap(): void
    {
        $dir = $this->dirPublik();
        $this->buatBuild($dir, false);

        $this->assertNotSame([], Aset::masalahBuild());
        $this->assertSame(Aset::DARURAT, Aset::sumber(),
            'Manifest yang menunjuk berkas hilang tidak boleh dipakai — CSS-nya akan 404.');
    }

    public function test_halaman_login_memuat_satu_sumber_css_yang_konsisten(): void
    {
        $sumber = Aset::sumber();
        $html = (string) $this->get('/login')->assertOk()->getContent();

        if ($sumber === Aset::BUILD) {
            $this->assertStringContainsString('/build/assets/', $html);

            return;
        }

        if ($sumber === Aset::HOT) {
            $this->assertStringContainsString('@vite/client', $html);

            return;
        }

        // Jalur darurat: gaya komponen wajib ikut, dan tidak boleh ada rujukan
        // ke dev server yang tidak hidup.
        $this->assertStringContainsString('.navw a', $html,
            'Halaman harus memuat gaya darurat (layouts/_gaya-darurat) saat aset build belum ada.');
        $this->assertStringNotContainsString('127.0.0.1:', $html);
        $this->assertStringNotContainsString('localhost:5173', $html);
    }

    public function test_perintah_cek_aset_melaporkan_jalur_yang_dipakai(): void
    {
        $this->dirPublik();

        $this->artisan('siapbos:cek-aset')
            ->expectsOutputToContain('Sumber CSS yang dipakai halaman')
            ->assertExitCode(1);

        $dir = $this->dirPublik();
        $this->buatBuild($dir);

        $this->artisan('siapbos:cek-aset')
            ->expectsOutputToContain('aset hasil build')
            ->assertExitCode(0);
    }

    /**
     * Aset hasil `npm run build` WAJIB ikut repository.
     *
     * Laptop bendahara (dan paket portable) tidak punya Node.js. Sebelum aset
     * dikomit, setiap clone/paket tanpa `public/build` tampil polos tanpa CSS.
     */
    public function test_aset_build_ikut_repository(): void
    {
        Aset::pakaiDirektori(null);

        $this->assertFileExists(Aset::berkasManifest(),
            'public/build/manifest.json tidak ada — halaman akan tampil polos di mesin tanpa Node.js. '
            .'Jalankan: npm ci && npm run build');
        $this->assertSame([], Aset::masalahBuild(),
            'Manifest build ada tetapi berkas rujukannya tidak lengkap.');
        $this->assertSame(Aset::BUILD, Aset::sumber(),
            'Aset build yang dikomit harus dipakai sebagai sumber CSS default.');

        $css = $this->cssBuild();
        $this->assertNotSame('', $css);
        $this->assertGreaterThan(50_000, strlen($css),
            'CSS hasil build terlalu kecil — kemungkinan build gagal separuh.');
        $this->assertStringContainsString('.inp', $css, 'CSS hasil build tidak memuat kelas komponen.');
    }

    /**
     * CSS yang dikomit tidak boleh ketinggalan dari sumbernya.
     *
     * Setiap kelas di `@layer components` resources/css/app.css harus ada di
     * public/build/assets/app-*.css. Bila gagal: jalankan `npm run build` lalu
     * komit ulang hasilnya.
     */
    public function test_css_build_memuat_semua_kelas_komponen(): void
    {
        $sumber = (string) file_get_contents(resource_path('css/app.css'));
        $awal = strpos($sumber, '@layer components');
        $this->assertNotFalse($awal);

        $buka = strpos($sumber, '{', $awal);
        $dalam = 0;
        $komponen = '';
        for ($i = $buka, $n = strlen($sumber); $i < $n; $i++) {
            if ($sumber[$i] === '{') {
                $dalam++;
            } elseif ($sumber[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    $komponen = substr($sumber, $buka + 1, $i - $buka - 1);
                    break;
                }
            }
        }

        preg_match_all('/\.([A-Za-z][A-Za-z0-9_-]*)/', $komponen, $m);
        $kelas = array_values(array_unique($m[1]));
        $this->assertNotSame([], $kelas);

        // Escape CSS dibuka: `.w-2\/3` pada berkas hasil build.
        $css = str_replace('\\', '', $this->cssBuild());

        $hilang = [];
        foreach ($kelas as $kelasSatu) {
            if (! preg_match('/\.'.preg_quote($kelasSatu, '/').'(?![\w-])/', $css)) {
                $hilang[] = $kelasSatu;
            }
        }

        $this->assertSame([], $hilang,
            'CSS hasil build ketinggalan dari resources/css/app.css (kelas: '.implode(', ', $hilang)
            .'). Jalankan npm run build lalu komit public/build.');
    }

    /** Gabungan seluruh berkas CSS di public/build/assets. */
    private function cssBuild(): string
    {
        $berkas = glob(public_path('build/assets').DIRECTORY_SEPARATOR.'*.css') ?: [];
        $css = '';
        foreach ($berkas as $satu) {
            $css .= (string) file_get_contents($satu);
        }

        return $css;
    }

    /**
     * Jalur build tidak boleh bergantung pada manifest/berkas hot.
     *
     * Dulu halaman tampil polos bila manifest rusak atau berkas `hot` basi.
     * Sekarang cukup berkas CSS-nya ada: layout merujuknya lewat <link> biasa.
     */
    public function test_css_dipakai_walau_manifest_rusak(): void
    {
        $dir = $this->dirPublik();
        $this->buatBuild($dir);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json', '{rusak');

        $this->assertNotSame([], Aset::masalahBuild(), 'Manifest rusak memang harus dilaporkan.');
        $this->assertSame(Aset::BUILD, Aset::sumber(),
            'Berkas CSS ada → halaman harus tetap memakai build, bukan jatuh ke darurat.');
        $this->assertSame(['/build/assets/app.css'], Aset::cssUrl());
        $this->assertSame(['/build/assets/app.js'], Aset::jsUrl());
    }

    public function test_hot_basi_tidak_pernah_memakai_vite(): void
    {
        $dir = $this->dirPublik();
        $this->buatBuild($dir);
        $this->anggapServerDev(false);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'hot', 'http://127.0.0.1:5173');

        $this->assertSame(Aset::BUILD, Aset::sumber());
        $this->assertFalse(Aset::viteAktif(),
            '@vite hanya boleh dipakai saat dev server benar-benar hidup.');
    }

    public function test_tanpa_berkas_aset_halaman_tetap_ditata_gaya_darurat(): void
    {
        $this->dirPublik(); // kosong: tidak ada build, tidak ada hot

        $html = (string) $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('.navw a', $html,
            'Tanpa aset build, layout wajib menyertakan gaya komponen (mode darurat).');
        $this->assertStringNotContainsString('/build/assets/', $html,
            'Tidak boleh merujuk berkas build yang tidak ada (pasti 404 → halaman polos).');
        $this->assertStringNotContainsString('localhost:5173', $html);
    }

    public function test_diagnosa_aset_hanya_untuk_localhost(): void
    {
        $this->dirPublik();

        $this->get('/_aset')->assertOk()->assertSee('Diagnosa aset SiapBOSP');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/_aset')
            ->assertNotFound();
    }

    /**
     * Alat perbaikan tampilan harus membaca manifest, bukan menulis nama berkas
     * ber-hash di dalam skrip (hash berubah setiap CSS dibangun ulang).
     */
    public function test_skrip_perbaikan_tampilan_memakai_manifest(): void
    {
        $ps1 = base_path('launcher-windows/Perbaiki-Tampilan.ps1');
        $bat = base_path('launcher-windows/Perbaiki-Tampilan.bat');

        $this->assertFileExists($ps1, 'Skrip perbaikan tampilan untuk pengguna hilang.');
        $this->assertFileExists($bat);

        $isi = (string) file_get_contents($ps1);
        $this->assertStringContainsString('manifest.json', $isi);
        $this->assertStringContainsString('BacaManifest', $isi);
        $this->assertStringNotContainsString('app-BWZe9esl', $isi,
            'Nama berkas CSS ber-hash tidak boleh ditulis manual di skrip.');
        $this->assertMatchesRegularExpression('/build\/assets|raw\.githubusercontent/', $isi);

        $this->assertStringContainsString('Perbaiki-Tampilan.ps1', (string) file_get_contents($bat));
    }

    /**
     * Dokumen audit sempat menyebut nama berkas CSS ber-hash yang sudah basi
     * (`app-BWZe9esl.css`) pada tabel "perbaikan manual"; pembaca yang
     * mengunduhnya akan mendapat 404 dan halaman tetap polos. Nama ber-hash
     * hanya boleh hidup di manifest, bukan di dokumen.
     */
    public function test_dokumen_tidak_menulis_nama_berkas_aset_yang_sudah_basi(): void
    {
        $isi = (string) file_get_contents(base_path('docs/AUDIT-CSS-HALAMAN.md'));

        preg_match_all('/assets\/app-[A-Za-z0-9_-]+\.(?:css|js)/', $isi, $cocok);
        foreach (array_unique($cocok[0]) as $sebutan) {
            $berkas = base_path('public/build/'.str_replace('assets/', 'assets/', $sebutan));
            $this->assertFileExists($berkas,
                'Dokumen menyebut '.$sebutan.' yang tidak ada di build — hash basi menyesatkan pembaca.');
        }
    }

    /** Nilai satu direktif CSP tanpa tertukar dengan direktif berawalan sama. */
    private function direktif(string $csp, string $nama): string
    {
        foreach (explode(';', $csp) as $bagian) {
            $bagian = trim($bagian);
            if (str_starts_with($bagian, $nama.' ')) {
                return substr($bagian, strlen($nama) + 1);
            }
        }

        return '';
    }
}
