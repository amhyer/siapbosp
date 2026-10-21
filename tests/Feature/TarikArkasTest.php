<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\TarikArkasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tombol 1-klik "Tarik Data ARKAS" di Pengaturan (Utility → Profil).
 * Kartu & aksi hanya aktif di aplikasi portabel di laptop sekolah
 * (TarikArkasService::tersedia()). Ujung-ke-ujung agen nyata diuji manual
 * pada paket portabel (docs/GAMBARAN-ARKAS-4.md §15).
 */
class TarikArkasTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000001', 'nama' => 'SD Negeri Uji', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->operator = User::create([
            'name' => 'Operator Uji', 'email' => 'op@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
    }

    public function test_kartu_tarik_tidak_tampil_di_server_pusat(): void
    {
        $this->actingAs($this->operator)
            ->get('/utility/profil')
            ->assertOk()
            ->assertSee('Token API')
            ->assertDontSee('Tarik Data ARKAS');
    }

    public function test_tarik_menolak_tahun_tidak_valid(): void
    {
        $this->actingAs($this->operator)
            ->from('/utility/profil')
            ->post('/utility/tarik-arkas', ['tahun' => 2010])
            ->assertRedirect('/utility/profil')
            ->assertSessionHasErrors('tahun');
    }

    public function test_tarik_ditolak_di_luar_portabel(): void
    {
        $this->assertFalse(TarikArkasService::tersedia());

        $this->actingAs($this->operator)
            ->from('/utility/profil')
            ->post('/utility/tarik-arkas', ['tahun' => 2025])
            ->assertRedirect('/utility/profil')
            ->assertSessionHas('error', 'Tarik ARKAS hanya tersedia di aplikasi portabel di laptop sekolah. Di server pusat, pakai unggah Excel manual di halaman Impor ARKAS.');
    }

    public function test_kepsek_boleh_membuka_profil_muka_tarik(): void
    {
        $kepsek = User::create([
            'name' => 'Kepsek Uji', 'email' => 'kepsek@tes.test', 'password' => 'password',
            'role' => 'kepsek', 'school_id' => $this->sekolah->id,
        ]);

        $this->actingAs($kepsek)
            ->get('/utility/profil')
            ->assertOk()
            ->assertDontSee('Tarik Data ARKAS');
    }

    /** Akun tanpa sekolah (mis. dinas) harus ditolak — bukan 500. */
    public function test_tanpa_sekolah_dialihkan_dengan_pesan(): void
    {
        $bujang = User::create([
            'name' => 'Tanpa Sekolah', 'email' => 'kosong@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => null,
        ]);

        $this->actingAs($bujang)
            ->from('/utility/profil')
            ->post('/utility/tarik-arkas', ['tahun' => 2025])
            ->assertRedirect('/utility/profil')
            ->assertSessionHas('error', 'Akun Anda tidak terikat ke sekolah mana pun.');
    }

    public function test_path_db_arkas_arahnya_benar(): void
    {
        $path = TarikArkasService::pathDb();
        $this->assertNotSame('', $path);
        $this->assertSame('\\Arkas\\arkas.db', substr($path, -15));
    }
}
