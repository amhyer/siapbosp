<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kunci palet ARKAS 4.3.1.
 *
 * Nilai diuji = design token resmi yang tertanam di aplikasi ARKAS Desktop
 * 4.3.1 (lihat docs/ARKAS-4.3.1-RUJUKAN.md). Test ini menjaga agar
 * resources/css/app.css tidak diam-diam kembali ke palet indigo/slate bawaan
 * Tailwind — cukup di situ letak seluruh tampilan aplikasi.
 */
class TokenArkasTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();

        $this->css = (string) file_get_contents(resource_path('css/app.css'));
    }

    private function nilai(string $token): ?string
    {
        if (preg_match('/--'.preg_quote($token, '/').':\s*(#[0-9a-fA-F]{3,8});/', $this->css, $m) !== 1) {
            return null;
        }

        return strtolower($m[1]);
    }

    public function test_aksi_utama_memakai_biru_arkas(): void
    {
        // blue-70 #0b5fef: tombol aksi utama ARKAS. View memakai indigo-600.
        $this->assertSame('#0b5fef', $this->nilai('color-indigo-600'));
        $this->assertSame('#0b5fef', $this->nilai('color-brand-600'));
    }

    public function test_status_memakai_warna_semantik_arkas(): void
    {
        $this->assertSame('#e02d38', $this->nilai('color-rose-600'));   // red-60
        $this->assertSame('#eda62b', $this->nilai('color-amber-400'));  // orange-40
        $this->assertSame('#117e47', $this->nilai('color-emerald-700')); // green-70
        $this->assertSame('#2f3031', $this->nilai('color-slate-900'));  // gray-90
    }

    public function test_token_semantik_tersedia_untuk_cetak_dan_dokumen(): void
    {
        // Nama token sengaja sama dengan paket ARKAS agar mudah dicocokkan.
        $harapan = [
            'arkas-action' => '#0b5fef',
            'arkas-action-hovered' => '#054bcc',
            'arkas-action-pressed' => '#103178',
            'arkas-primary' => '#0b5ae2',
            'arkas-critical' => '#c82236',
            'arkas-success' => '#35825a',
            'arkas-warning' => '#ffc453',
            'arkas-informational' => '#4c8df6',
            'arkas-text-default' => '#2f3031',
        ];

        foreach ($harapan as $token => $nilai) {
            $this->assertSame($nilai, $this->nilai($token), 'Token --'.$token.' menyimpang dari ARKAS 4.3.1.');
        }
    }

    public function test_bayangan_kartu_memakai_elevation_01_arkas(): void
    {
        $this->assertStringContainsString('--arkas-shadow-card: 0px 1px 1px rgba(0, 0, 0, 0.08)', $this->css);
        $this->assertStringContainsString('box-shadow: var(--arkas-shadow-card);', $this->css);
    }

    /** Palet indigo/slate bawaan Tailwind tidak boleh muncul lagi sebagai nilai token. */
    public function test_tidak_kembali_ke_palet_bawaan_tailwind(): void
    {
        foreach (['#4f46e5', '#4338ca', '#eef2ff', '#0f172a', '#1e293b', '#f1f5f9'] as $bawaan) {
            $this->assertStringNotContainsString($bawaan, strtolower($this->css),
                'Warna bawaan Tailwind '.$bawaan.' masih dipakai sebagai token ARKAS.');
        }
    }
}
