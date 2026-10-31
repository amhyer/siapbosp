<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regresi pemuatan CSS per halaman (audit tampilan).
 *
 * Fokus pada hal yang tidak tertangkap tes lain: halaman yang tidak memakai
 * aset Vite (layouts.print) dan jalur fallback tanpa `public/build`. Semua
 * pemeriksaan bersifat statis atas berkas view/CSS sehingga stabil tanpa
 * browser maupun hasil build.
 */
class CssHalamanTest extends TestCase
{
    /** Kelas yang bukan CSS: penanda hook JavaScript / hasil render dinamis. */
    private const KELAS_HOOK_JS = [
        'blnInp', 'hrg', 'jml', 'nP', 'nR', 'nilai', 'nilp', 'pjk', 'qty',
        'rekBelanja', 'rekSel', 'satInp', 'sshSel', 'sumberRow', 'trf',
        'uraianInp', 'rkt-biaya', 'group-hover',
    ];

    /** Ambil isi blok `@layer components { … }` dengan pencocokan kurung kurawal. */
    private function lapisanKomponen(string $css): string
    {
        $awal = strpos($css, '@layer components');
        $this->assertNotFalse($awal, 'resources/css/app.css tidak punya @layer components.');

        $buka = strpos($css, '{', $awal);
        $dalam = 0;
        for ($i = $buka, $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === '{') {
                $dalam++;
            } elseif ($css[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($css, $buka + 1, $i - $buka - 1);
                }
            }
        }

        $this->fail('Blok @layer components tidak tertutup dengan benar.');
    }

    /**
     * Kumpulan nama kelas yang didefinisikan pada potongan CSS.
     *
     * Escape CSS dibuka lebih dulu supaya nama kelasnya sama dengan yang
     * ditulis di markup: `.w-2\/3` → `w-2/3`, `.text-\[10px\]` → `text-[10px]`.
     */
    private function kelasDidefinisikan(string $css): array
    {
        preg_match_all('/\.((?:\\\\.|[A-Za-z0-9_-])+)/', $css, $m);

        $kelas = [];
        foreach ($m[1] as $token) {
            $token = str_replace('\\', '', $token);
            if (preg_match('/^[A-Za-z]/', $token)) {
                $kelas[] = $token;
            }
        }

        return array_values(array_unique($kelas));
    }

    /** Kumpulan kelas yang dipakai pada atribut class="…" sebuah view. */
    private function kelasDipakai(string $isi): array
    {
        $hasil = [];
        preg_match_all('/class\s*=\s*"([^"]*)"/', $isi, $m);

        foreach ($m[1] as $atribut) {
            // Buang ekspresi Blade & direktif, sisakan token kelas literal.
            $bersih = preg_replace('/\{\{.*?\}\}/s', ' ', $atribut);
            $bersih = preg_replace('/@[a-zA-Z]+(\([^)]*\))?/', ' ', (string) $bersih);

            foreach (preg_split('/\s+/', (string) $bersih) as $kelas) {
                $kelas = trim($kelas, "'\",;");
                if ($kelas !== '' && preg_match('/^[A-Za-z0-9!\-\[\]\/\.%#:_()]+$/', $kelas)) {
                    $hasil[$kelas] = true;
                }
            }
        }

        return array_keys($hasil);
    }

    public function test_setiap_halaman_memakai_layout_beraset(): void
    {
        $dikecualikan = [
            'welcome.blade.php',                 // sisa template Laravel, tidak dirutekan
            'laporan/lpj-pdf.blade.php',         // dirender Dompdf (CSS inline)
            'rkas/pdf.blade.php',                // dirender Dompdf (CSS inline)
            'laporan/ttd.blade.php',             // partial tanda tangan
            'laporan-arkas/_header.blade.php',   // partial
            'emails/notifikasi.blade.php',       // markdown mail
        ];

        $bermasalah = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $berkas) {
            if (! $berkas->isFile() || ! str_ends_with($berkas->getFilename(), '.blade.php')) {
                continue;
            }

            $relatif = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $berkas->getPathname());
            $relatif = str_replace('\\', '/', $relatif);

            // Layout, komponen, partial (awalan "_"), dan pengecualian di atas
            // tidak perlu @extends karena dirender di dalam layout beraset.
            if (str_starts_with($relatif, 'layouts/') || str_starts_with($relatif, 'components/')
                || str_starts_with(basename($relatif), '_')
                || in_array($relatif, $dikecualikan, true)) {
                continue;
            }

            if (! preg_match("/@extends\('(layouts\.[a-z]+)'/", (string) file_get_contents($berkas->getPathname()))) {
                $bermasalah[] = $relatif;
            }
        }

        $this->assertSame([], $bermasalah, 'View berikut tidak memuat layout beraset CSS: '.implode(', ', $bermasalah));
    }

    public function test_layout_memuat_aset_css_dengan_jalur_fallback(): void
    {
        $app = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $guest = (string) file_get_contents(resource_path('views/layouts/guest.blade.php'));

        foreach (['app' => $app, 'guest' => $guest] as $nama => $isi) {
            $this->assertStringContainsString(
                "@vite(['resources/css/app.css', 'resources/js/app.js'])", $isi,
                "layouts.{$nama} harus memuat CSS hasil build lewat @vite."
            );
            $this->assertStringContainsString('Aset::viteAktif()', $isi,
                "layouts.{$nama} harus memakai App\\Support\\Aset, bukan file_exists mentah.");
            $this->assertStringContainsString('@foreach (Aset::cssUrl() as $css)', $isi,
                "layouts.{$nama} harus merujuk CSS hasil build lewat <link> biasa "
                .'(tanpa Vite/manifest/hot, sehingga tidak bisa menunjuk aset yang tidak ada).');
            $this->assertStringContainsString("@include('layouts._gaya-darurat')", $isi,
                "layouts.{$nama} harus memuat gaya darurat pada cabang tanpa build.");
            $this->assertStringContainsString('src="/tailwind.js"', $isi,
                "layouts.{$nama} harus menyediakan tailwind.js pada cabang tanpa build.");
            $this->assertStringContainsString('Aset::tailwindJsAda()', $isi,
                "layouts.{$nama} tidak boleh merujuk /tailwind.js yang tidak ada.");
        }

        // Halaman cetak tidak memakai aset Vite; gayanya harus mandiri.
        $cetak = (string) file_get_contents(resource_path('views/layouts/print.blade.php'));
        $this->assertStringContainsString('<style>', $cetak);
        $this->assertStringNotContainsString('@vite', $cetak,
            'layouts.print sengaja mandiri agar dialog cetak tidak bergantung aset build.');
    }

    public function test_gaya_darurat_mencakup_semua_kelas_komponen_app_css(): void
    {
        $komponen = $this->lapisanKomponen((string) file_get_contents(resource_path('css/app.css')));
        $darurat = (string) file_get_contents(resource_path('views/layouts/_gaya-darurat.blade.php'));

        $hilang = [];
        foreach ($this->kelasDidefinisikan($komponen) as $kelas) {
            if (! preg_match('/\.'.preg_quote($kelas, '/').'(?![\w-])/', $darurat)) {
                $hilang[] = $kelas;
            }
        }

        $this->assertSame([], $hilang,
            'Kelas komponen app.css belum ada di layouts/_gaya-darurat.blade.php: '.implode(', ', $hilang));
    }

    public function test_halaman_cetak_hanya_memakai_kelas_yang_didefinisikan(): void
    {
        // Gaya yang selalu tersedia untuk halaman cetak: blok <style> di
        // layouts/print ditambah blok <style> lokal milik view itu sendiri.
        $gayaCetak = (string) file_get_contents(resource_path('views/layouts/print.blade.php'));
        preg_match_all('/<style>(.*?)<\/style>/s', $gayaCetak, $m);
        $dasar = $this->kelasDidefinisikan(implode("\n", $m[1]));

        $bermasalah = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $berkas) {
            if (! $berkas->isFile() || ! str_ends_with($berkas->getFilename(), '.blade.php')) {
                continue;
            }

            $isi = (string) file_get_contents($berkas->getPathname());
            if (! str_contains($isi, "@extends('layouts.print'")) {
                continue;
            }

            preg_match_all('/<style>(.*?)<\/style>/s', $isi, $lokal);
            $tersedia = array_merge($dasar, $this->kelasDidefinisikan(implode("\n", $lokal[1])));

            foreach ($this->kelasDipakai($isi) as $kelas) {
                if (in_array($kelas, self::KELAS_HOOK_JS, true)) {
                    continue;
                }
                if (! in_array($kelas, $tersedia, true)) {
                    $bermasalah[] = $berkas->getFilename().': '.$kelas;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($bermasalah)),
            'Halaman cetak memakai kelas tanpa definisi CSS (tambahkan aturannya pada blok <style> '
            .'layouts/print.blade.php atau <style> lokal view): '.implode(', ', array_unique($bermasalah)));
    }

    public function test_kaki_halaman_cetak_bernomor_ala_arkas(): void
    {
        // ARKAS mencetak "Halaman X dari Y" di kaki setiap dokumen cetak.
        $cetak = (string) file_get_contents(resource_path('views/layouts/print.blade.php'));

        $this->assertStringContainsString('@bottom-right', $cetak);
        $this->assertStringContainsString('counter(page)', $cetak);
        $this->assertStringContainsString('counter(pages)', $cetak);
    }
}
