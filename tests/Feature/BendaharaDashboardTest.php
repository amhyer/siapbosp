<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BendaharaDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private User $dinas;

    private School $sekolah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000088',
            'nama' => 'SD Negeri Harapan Bendahara',
            'jenjang' => 'SD',
            'kecamatan' => 'Biringkanaya',
            'kab_kota' => 'Makassar',
            'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->op = User::create([
            'name' => 'Bendahara Sekolah',
            'email' => 'bendahara@sekolah.id',
            'password' => 'password',
            'role' => 'operator',
            'school_id' => $this->sekolah->id,
        ]);

        $this->dinas = User::create([
            'name' => 'Admin Dinas Pendidikan',
            'email' => 'admin@dinas.id',
            'password' => 'password',
            'role' => 'dinas',
            'school_id' => null,
        ]);
    }

    /** Verifikasi 1 Aturan Emas, 3 Langkah Utama, dan Lampu Indikator pada Dashboard. */
    public function test_dashboard_memuat_1_aturan_emas_3_langkah_utama_dan_indikator_spj(): void
    {
        $res = $this->actingAs($this->op)->withSession(['tahun' => 2026])->get('/dashboard');

        $res->assertOk();

        // 1. Pesan 1 Aturan Emas
        $res->assertSee('1 Aturan Emas Bendahara');
        $res->assertSee('Bapak/Ibu Bendahara tetap bekerja normal di ARKAS 4');
        $res->assertSee('Tarik Data ➔ Cetak Dokumen SPJ/Kwitansi ➔ Kirim ke Dinas');
        $res->assertSee('Anda tidak perlu menginput ulang belanja dari nol');

        // 2. 3 Langkah Utama (Action Cards)
        $res->assertSee('STATUS BOSP BULAN INI:');
        $res->assertSee('TARIK DARI ARKAS');
        $res->assertSee('CETAK SPJ &amp; KWITANSI', false);
        $res->assertSee('KIRIM KE DINAS');
        $res->assertSee('Buka Berkas Cetak');
        $res->assertSee('Kirim Laporan Sekarang');

        // 3. Pintasan 1-klik Cetak SPJ
        $res->assertSee('Cetak SPJ Bulan Ini (1-Klik)');

        // 4. Lampu Indikator Kesiapan Dokumen SPJ
        $res->assertSee('Lampu Indikator Kesiapan Dokumen SPJ');
        $res->assertSee('ARKAS');
        $res->assertSee('Pajak');
        $res->assertSee('Saldo Kas');
    }

    /** Verifikasi branding topbar sekolah memuat SiapBOSP Bendahara. */
    public function test_branding_topbar_bendahara(): void
    {
        $res = $this->actingAs($this->op)->withSession(['tahun' => 2026])->get('/dashboard');
        $res->assertOk();
        $res->assertSee('SiapBOSP Bendahara');
        $res->assertSee('Bendahara');
    }

    /** Verifikasi branding topbar dinas memuat SiapBOSP Pusat Dinas. */
    public function test_branding_topbar_dinas(): void
    {
        $res = $this->actingAs($this->dinas)->withSession(['tahun' => 2026])->get('/dinas');
        $res->assertOk();
        $res->assertSee('SiapBOSP Pusat Dinas');
        $res->assertSee('Pusat Dinas');
    }
}
