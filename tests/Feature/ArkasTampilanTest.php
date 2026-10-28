<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kontrak tampilan global ala ARKAS 4.
 *
 * Semua halaman memakai utilitas `indigo-*` dan kelas komponen di
 * `resources/css/app.css`. Palet ARKAS (biru #0b5fef = --color-action-default) dipasang sekali di
 * blok `@theme`, jadi satu perubahan di sana harus mengubah SELURUH halaman.
 *
 * Tes ini menjaga tiga hal yang mudah rusak tanpa disadari:
 *   1. token & bentuk komponen benar-benar ada di CSS hasil build,
 *   2. aset build tidak tertinggal (lupa `npm run build` → tampilan lama),
 *   3. jalur darurat (tanpa build) memakai palet yang sama.
 */
class ArkasTampilanTest extends TestCase
{
    private const HEX_LAMA = ['#4f46e5', '#4338ca', '#6366f1', '#e0e7ff', '#c7d2fe', '#eef2ff'];

    /** Nilai biru ARKAS yang dipasang di app.css. */
    private function biruSumber(): string
    {
        $sumber = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression(
            '/--color-indigo-600:\s*(#[0-9a-fA-F]{6})/',
            $sumber,
            'app.css harus memetakan indigo-600 ke biru ARKAS.'
        );
        preg_match('/--color-indigo-600:\s*(#[0-9a-fA-F]{6})/', $sumber, $m);

        return strtolower($m[1]);
    }

    /** Isi CSS hasil build yang dirujuk manifest. */
    private function cssBuild(): string
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'public/build/manifest.json belum ada — jalankan npm run build.');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $relatif = $manifest['resources/css/app.css']['file'] ?? null;
        $this->assertNotNull($relatif, 'Manifest tidak memuat entri resources/css/app.css.');

        $berkas = public_path('build/'.$relatif);
        $this->assertFileExists($berkas, 'Aset CSS yang dirujuk manifest tidak ada: '.$relatif);

        return (string) file_get_contents($berkas);
    }

    public function test_palet_arkas_terpasang_di_token_css(): void
    {
        $biru = $this->biruSumber();
        $sumber = (string) file_get_contents(resource_path('css/app.css'));

        // Seluruh langkah skala indigo dipetakan supaya chip/ring ikut biru,
        // bukan hanya tombol utama.
        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900] as $langkah) {
            $this->assertStringContainsString('--color-indigo-'.$langkah.':', $sumber);
        }

        $this->assertStringContainsString('--color-brand-600: '.$biru, $sumber);
        // #0b5fef = token --color-action-default ARKAS 4.3.1 (design token resmi).
        $this->assertSame('#0b5fef', $biru, 'Biru ARKAS berubah tanpa memperbarui dokumentasi tampilan.');
    }

    public function test_aset_build_memakai_palet_dan_bentuk_arkas(): void
    {
        $biru = $this->biruSumber();
        $css = $this->cssBuild();

        // 1. Token biru ikut ter-build (kalau lupa `npm run build`, ini gagal).
        $this->assertMatchesRegularExpression('/--color-indigo-600:\s*'.preg_quote($biru, '/').'/', $css);

        // 2. Sudut tegas: kartu tidak lagi membulat besar dan tanpa bayangan.
        $this->assertStringContainsString('--radius-2xl:10px', $css);
        $this->assertMatchesRegularExpression('/\.card\{[^}]*border-radius:var\(--radius-xl\)/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.card\{[^}]*box-shadow/', $css);

        // 3. Chip memakai sudut kecil (bukan kapsul) seperti ARKAS.
        $this->assertMatchesRegularExpression('/\.badge\{[^}]*border-radius:\.25rem/', $css);

        // 4. Tabel lebih rapat: padding sel 0.5rem/0.625rem.
        $this->assertMatchesRegularExpression('/\.tbl th\{[^}]*padding-block:calc\(var\(--spacing\) \* 2\)/', $css);

        // 5. Tombol utama memakai token biru ARKAS.
        $this->assertMatchesRegularExpression('/\.btn\{[^}]*background-color:var\(--color-indigo-600\)/', $css);
    }

    public function test_jalur_darurat_tanpa_build_memakai_palet_yang_sama(): void
    {
        $biru = $this->biruSumber();
        $darurat = (string) file_get_contents(resource_path('views/layouts/_gaya-darurat.blade.php'));

        $this->assertStringContainsString($biru, $darurat, 'layouts._gaya-darurat memakai biru ARKAS.');

        foreach (self::HEX_LAMA as $lama) {
            $this->assertStringNotContainsString($lama, $darurat, 'Hex indigo lama tertinggal di _gaya-darurat: '.$lama);
        }
    }

    public function test_theme_color_dan_manifest_memakai_biru_arkas(): void
    {
        $biru = $this->biruSumber();

        foreach (['app', 'guest'] as $layout) {
            $html = (string) file_get_contents(resource_path('views/layouts/'.$layout.'.blade.php'));
            $this->assertStringContainsString('<meta name="theme-color" content="'.$biru.'">', $html);
        }

        $manifest = (string) file_get_contents(public_path('manifest.webmanifest'));
        $this->assertStringContainsString('"theme_color": "'.$biru.'"', $manifest);
    }

    public function test_tidak_ada_sisa_hex_indigo_lama_di_view(): void
    {
        $temuan = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $berkas) {
            if (! $berkas->isFile()) {
                continue;
            }
            $isi = strtolower((string) file_get_contents($berkas->getPathname()));
            foreach (self::HEX_LAMA as $lama) {
                if (str_contains($isi, $lama)) {
                    $temuan[] = $berkas->getFilename().': '.$lama;
                }
            }
        }

        $this->assertSame([], $temuan,
            'Warna indigo lama masih ditulis manual di view (pakai token/kelas ARKAS): '.implode(', ', $temuan));
    }
}
