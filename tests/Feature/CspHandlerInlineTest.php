<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi audit R-03: handler event inline (`onclick`, `onsubmit`, `onchange`,
 * `oninput`) sudah dipindah ke `data-*` + `addEventListener`, sehingga CSP
 * tidak lagi mengizinkan `script-src-attr 'unsafe-inline'`.
 *
 * Tiga lapis pemeriksaan:
 * 1. header CSP yang benar-benar dikirim;
 * 2. seluruh berkas `resources/views/**\/*.blade.php` (termasuk template yang
 *    tidak selalu dirender) bebas dari atribut handler;
 * 3. markup yang dirender untuk beberapa halaman kunci bebas dari atribut
 *    handler, dan lapisan interaksi pengganti benar-benar ikut dimuat.
 */
class CspHandlerInlineTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();

        $s = School::create(['npsn' => '90000066', 'nama' => 'SD CSP', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->op = User::create(['name' => 'Op CSP', 'email' => 'op-csp@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);
        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Stage::create(['tahun' => 2025, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
    }

    private function direktif(string $csp, string $nama): string
    {
        // Cocokkan nama direktif secara persis: "script-src" tidak boleh
        // mengambil nilai "script-src-attr".
        if (preg_match('/(?:^|;\s*)'.preg_quote($nama, '/').'\s+([^;]+)/', $csp, $m) !== 1) {
            return '';
        }

        return trim($m[1]);
    }

    public function test_csp_menutup_handler_atribut_inline(): void
    {
        // /login tidak butuh sesi; header CSP berlaku juga untuk halaman publik.
        $csp = (string) $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertSame("'none'", $this->direktif($csp, 'script-src-attr'),
            'script-src-attr harus ditutup penuh, bukan diisi unsafe-inline.');
        $this->assertStringNotContainsString("'unsafe-inline'", $this->direktif($csp, 'script-src'),
            'script-src tidak boleh membuka inline script tanpa nonce.');
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]+'/", $this->direktif($csp, 'script-src'));
        $this->assertStringNotContainsString("script-src-attr 'unsafe-inline'", $csp);
    }

    public function test_tidak_ada_handler_atribut_inline_di_view_blade(): void
    {
        $pola = '/\bon(?:click|change|submit|input|load|error|focus|blur|dblclick|mouse\w+|key\w+|touch\w+)\s*=\s*["\']/i';
        $pelanggaran = [];

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($berkas as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // Komentar Blade tidak ikut dikirim ke browser, jadi tidak dinilai.
            $isi = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file->getPathname()));

            if (preg_match_all($pola, $isi, $cocok, PREG_OFFSET_CAPTURE)) {
                foreach ($cocok[0] as [$teks, $offset]) {
                    $baris = substr_count(substr($isi, 0, (int) $offset), "\n") + 1;
                    $pelanggaran[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname())
                        .':'.$baris.' ('.$teks.')';
                }
            }
        }

        $this->assertSame([], $pelanggaran, 'Handler atribut inline masih tersisa: '.implode(', ', $pelanggaran));
    }

    public function test_markup_yang_dirender_tidak_memuat_handler_atribut_inline(): void
    {
        $halaman = [
            '/dashboard',
            '/pembukuan/bku',
            '/pembukuan/buku-pembantu?semester=2',
            '/laporan/tahap',
            '/pembukuan/sptjm',
        ];

        foreach ($halaman as $url) {
            $html = $this->actingAs($this->op)->withSession(['tahun' => 2025])->get($url)->assertOk()->getContent();

            preg_match_all('/<[a-zA-Z][^>]*>/', (string) $html, $tag);
            $tersisa = array_values(array_filter($tag[0], fn (string $t): bool => (bool) preg_match(
                '/\son(?:click|change|submit|input|load|error|focus|blur|dblclick|mouse\w+|key\w+|touch\w+)\s*=/i',
                $t
            )));

            $this->assertSame([], array_slice($tersisa, 0, 5), $url.' masih memuat handler atribut inline.');
        }
    }

    public function test_lapisan_interaksi_non_inline_ikut_dirender(): void
    {
        $bku = $this->actingAs($this->op)->withSession(['tahun' => 2025])->get('/pembukuan/bku')->assertOk()->getContent();

        // Form tutup BKU: konfirmasi lewat data-konfirmasi, filter bulan lewat
        // data-kirim-otomatis, keduanya ditangani listener delegasi.
        $this->assertStringContainsString('data-konfirmasi="Tutup BKU', $bku);
        $this->assertStringContainsString('data-kirim-otomatis', $bku);
        $this->assertStringContainsString("closest('[data-konfirmasi]')", $bku);
        $this->assertStringContainsString("hasAttribute('data-kirim-otomatis')", $bku);

        // Kerangka aplikasi: buka/tutup sidebar dan grup menu tanpa onclick.
        $this->assertStringContainsString('data-sidebar-buka', $bku);
        $this->assertStringContainsString('data-sidebar-tutup', $bku);
        $this->assertStringContainsString('data-grup', $bku);

        // layouts.print juga memuat lapisan interaksi untuk tombol cetak.
        $cetak = $this->actingAs($this->op)->withSession(['tahun' => 2025])
            ->get('/pembukuan/buku-pembantu?semester=2')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-cetak-halaman', $cetak);
        $this->assertStringContainsString("closest('[data-cetak-halaman]')", $cetak);
    }
}
