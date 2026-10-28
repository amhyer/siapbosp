<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tipografi ARKAS 4.3.1 = Inter.
 *
 * Berkas fon disimpan sendiri di public/fonts (bukan CDN) karena CSP aplikasi
 * hanya mengizinkan `font-src 'self'`. Uji ini memastikan tiga jalur pemuatan
 * gaya memakai fon yang sama dan berkasnya benar-benar ada — kalau @font-face
 * menunjuk berkas yang tidak ada, teks diam-diam kembali ke fon sistem.
 */
class TipografiInterTest extends TestCase
{
    private const BERKAS = '/fonts/inter-latin-wght-normal.woff2';

    public function test_berkas_fon_benar_benar_ada_dan_berformat_woff2(): void
    {
        $path = public_path(ltrim(self::BERKAS, '/'));

        $this->assertFileExists($path, 'Berkas Inter hilang dari public/fonts.');
        $this->assertGreaterThan(20_000, (int) filesize($path), 'Berkas fon terlalu kecil — mungkin rusak.');
        $this->assertSame('wOF2', (string) file_get_contents($path, false, null, 0, 4),
            'Berkas di public/fonts bukan WOFF2 yang sah.');
    }

    public function test_lisensi_fon_ikut_disimpan(): void
    {
        $lisensi = (string) file_get_contents(public_path('fonts/Inter-OFL.txt'));

        $this->assertStringContainsString('SIL Open Font License', $lisensi,
            'Lisensi Inter wajib ikut karena fonnya didistribusikan ulang.');
    }

    public function test_app_css_mendeklarasikan_inter_sebagai_fon_utama(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString("font-family: 'Inter'", $css);
        $this->assertStringContainsString(self::BERKAS, $css);
        $this->assertStringContainsString("--font-sans: 'Inter',", $css,
            'Fon utama aplikasi harus Inter (tipografi ARKAS 4.3.1).');
        $this->assertStringNotContainsString("--font-sans: 'Instrument Sans'", $css,
            'Instrument Sans tidak pernah dimuat, jadi tidak boleh jadi fon utama.');
    }

    public function test_aset_hasil_build_memuat_font_face_dan_berkas_inter(): void
    {
        $berkas = glob(public_path('build/assets/app-*.css')) ?: [];
        $this->assertNotEmpty($berkas, 'Aset build CSS tidak ditemukan — jalankan npm run build.');

        $css = (string) file_get_contents($berkas[0]);
        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString('inter-latin-wght-normal.woff2', $css,
            'Aset build belum memuat @font-face Inter — build perlu dijalankan ulang.');
    }

    public function test_halaman_cetak_dan_gaya_darurat_memakai_inter(): void
    {
        foreach ([
            'layouts/print.blade.php' => 'dokumen cetak',
            'layouts/_gaya-darurat.blade.php' => 'gaya darurat (tanpa aset build)',
        ] as $view => $nama) {
            $isi = (string) file_get_contents(resource_path('views/'.$view));

            $this->assertStringContainsString(self::BERKAS, $isi, $nama.' harus memuat @font-face Inter.');
            $this->assertStringContainsString("'Inter'", $isi, $nama.' harus memakai Inter lebih dulu.');
        }
    }

    public function test_csp_mengizinkan_fon_dari_server_sendiri(): void
    {
        $middleware = (string) file_get_contents(app_path('Http/Middleware/KeamananHeader.php'));

        $this->assertMatchesRegularExpression("/font-src 'self'/", $middleware,
            "CSP harus mengizinkan font-src 'self' agar Inter lokal bisa dimuat.");
    }
}
