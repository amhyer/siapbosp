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
    private function anggapServerDev(bool $hidup): void
    {
        Aset::pakaiPenguji(fn (string $url): bool => $hidup);
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
