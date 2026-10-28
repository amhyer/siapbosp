<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etalase gaya (/gaya) — halaman pemeriksa token & komponen ARKAS 4.3.1.
 *
 * Halaman ini bukan fitur akuntansi; gunanya memastikan token yang dibaca dari
 * bundel ARKAS benar-benar terpasang dan komponennya tampil. Karena itu yang
 * diuji: halaman terbuka, memakai kerangka aplikasi, memakai var(--arkas-*),
 * dan tidak memakai penanganan event sebaris (CSP `script-src-attr 'none'`).
 */
class GayaHalamanTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();

        $s = School::create(['npsn' => '90000404', 'nama' => 'SD Gaya', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->op = User::create(['name' => 'Op Gaya', 'email' => 'gaya@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);

        $this->actingAs($this->op);
    }

    private function halaman(): string
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('gaya'))
            ->assertOk()
            ->getContent();
    }

    public function test_halaman_etalase_terbuka_di_dalam_kerangka_aplikasi(): void
    {
        $html = $this->halaman();

        $this->assertStringContainsString('Gaya antarmuka SiapBOSP', $html);
        $this->assertStringContainsString('navw', $html, 'Halaman gaya harus memakai layouts.app.');

        foreach (['1. Token warna', '4. Tombol', '5. Label status', '6. Formulir', '7. Tabel', '8. Daftar token'] as $judul) {
            $this->assertStringContainsString($judul, $html);
        }
    }

    /** Kotak warna harus memanggil token, bukan menulis hex langsung. */
    public function test_swatch_memakai_token_bukan_hex_lepas(): void
    {
        $html = $this->halaman();

        foreach (['--arkas-action', '--arkas-primary', '--arkas-text-default', '--arkas-surface-neutral-high', '--arkas-critical'] as $token) {
            $this->assertStringContainsString('var('.$token.')', $html, 'Swatch '.$token.' hilang.');
        }

        $this->assertStringContainsString('#0b5fef', $html, 'Nilai token ARKAS harus tertulis sebagai keterangan.');
    }

    /** CSP `script-src-attr 'none'`: tidak boleh ada on*="..." di halaman. */
    public function test_tidak_ada_event_handler_sebaris(): void
    {
        $html = $this->halaman();

        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html,
            'Halaman gaya tidak boleh memakai onclick/onchange sebaris (diblokir CSP).');
    }

    /** Komponen nyata harus ikut dirender supaya halaman ini berguna sebagai pembanding. */
    public function test_komponen_utama_dirender(): void
    {
        $html = $this->halaman();

        foreach (['class="btn"', 'class="btn2"', 'class="btn-danger"', 'class="inp"', 'class="tbl"', 'class="badge'] as $kelas) {
            $this->assertStringContainsString($kelas, $html, 'Komponen '.$kelas.' tidak tampil di etalase.');
        }
    }
}
