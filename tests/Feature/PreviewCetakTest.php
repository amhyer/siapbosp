<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preview cetak: tombol cetak (Buku Pembantu, Rekap Realisasi, SPTJM,
 * Cetak BKU) tak lagi membuka tab HTML — halaman cetak dimuat di iframe
 * tersembunyi lalu dialog cetak browser langsung dibuka.
 *
 * Halaman cetak yang bisa diisi (SPJ) tetap dibuka sebagai halaman biasa
 * karena isiannya harus diklik & diisi pengguna.
 */
class PreviewCetakTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();

        $s = School::create(['npsn' => '90000077', 'nama' => 'SD Preview', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->op = User::create(['name' => 'Op Preview', 'email' => 'op-preview@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);
        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Stage::create(['tahun' => 2025, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $this->actingAs($this->op);
    }

    public function test_halaman_bku_memakai_preview_cetak_bukan_tab_baru(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/pembukuan/bku')->assertOk()->getContent();

        // Tombol cetak memakai data-cetak (preview), bukan target="_blank".
        $this->assertStringContainsString('data-cetak="Buku Pembantu"', $html);
        $this->assertStringContainsString('data-cetak="Rekap Realisasi"', $html);
        $this->assertStringContainsString('data-cetak="SPTJM"', $html);
        $this->assertStringContainsString('data-cetak="Cetak BKU"', $html);
        $this->assertStringNotContainsString('target="_blank" class="btn2 btn">📚 Buku Pembantu', $html);
    }

    public function test_helper_preview_cetak_ada_di_layout(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/pembukuan/bku')->assertOk()->getContent();

        $this->assertStringContainsString('siapbosCetakPreview', $html);
        $this->assertStringContainsString("closest('a[data-cetak]')", $html);
    }

    public function test_halaman_cetak_buku_pembantu_tetap_bisa_diakses_langsung(): void
    {
        $this->withSession(['tahun' => 2025])
            ->get('/pembukuan/buku-pembantu?semester=2')
            ->assertOk()
            ->assertSee('BUKU PEMBANTU RINCIAN OBJEK BELANJA');
    }

    public function test_dashboard_memakai_preview_untuk_lpj_per_tahap(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-cetak="LPJ per Tahap"', $html);
    }

    /** Halaman cetak (layouts.print) juga memuat helper preview —
     *  supaya tombol di dalamnya (mis. "LPJ Lengkap") jadi preview, bukan
     *  membuka tab HTML baru. */
    public function test_layout_cetak_memuat_helper_preview(): void
    {
        $html = $this->withSession(['tahun' => 2025])
            ->get('/pembukuan/buku-pembantu?semester=2')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('siapbosCetakPreview', $html);
    }

    /**
     * Halaman yang定番の dari sidebar (LPJ per Tahap, SPTJM) harus memakai
     * kerangka aplikasi — sidebar, header, dan menu — bukan halaman HTML polos.
     * Isinya tetap berformat dokumen saat dicetak (wrapper .dokumen + aturan
     *
     * @media print).
     */
    public function test_halaman_dokumen_memakai_kerangka_aplikasi_bukan_html_polos(): void
    {
        foreach (['/laporan/tahap', '/pembukuan/sptjm'] as $url) {
            $html = $this->withSession(['tahun' => 2025])->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('id="sidebar"', $html, $url.' harus menampilkan sidebar');
            $this->assertStringContainsString('Kertas Kerja (RKAS)', $html, $url.' harus menampilkan menu aplikasi');
            $this->assertStringContainsString('class="dokumen"', $html, $url.' harus membungkus isi sebagai dokumen');
            $this->assertStringContainsString('@media print', $html, $url.' harus punya aturan cetak');
        }
    }
}
