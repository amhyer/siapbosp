<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\TarikArkasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sisi laptop: tombol 1-klik "Tarik Data dari ARKAS" di sidebar aplikasi
 * portabel (POST /utility/tarik-arkas). Website pusat tidak pernah membaca
 * database ARKAS — ia hanya menyediakan unduhan Aplikasi Desktop lewat halaman
 * /aplikasi-desktop. Kartu & aksi hanya aktif di aplikasi portabel
 * (TarikArkasService::tersedia()). Ujung-ke-ujung agen nyata diuji manual pada
 * paket portabel (docs/GAMBARAN-ARKAS-4.md §15).
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

    /** Rute GET "Sinkron ARKAS" sudah tidak ada — penarikan tinggal lewat sidebar + POST tarik. */
    public function test_rute_sinkron_arkas_tidak_ada(): void
    {
        $this->assertFalse(Route::has('tarik-arkas.sinkron'),
            'Halaman GET tarik-arkas.sinkron harus dihapus; satu-satunya penarikan lewat sidebar (POST tarik).');

        $this->get('/utility/sinkron-arkas')->assertNotFound();
    }

    /** Website pusat wajib menyediakan halaman unduh Aplikasi Desktop sebagai pintu tarik. */
    public function test_halaman_aplikasi_desktop_dapat_diakses_operator(): void
    {
        $this->actingAs($this->operator)
            ->get(route('aplikasi-desktop'))
            ->assertOk()
            ->assertSee('Aplikasi Desktop');
    }

    /** Dashboard pusat wajib menampilkan CTA unduh Aplikasi Desktop (bukan tombol tarik). */
    public function test_dashboard_menyediakan_cta_unduh_aplikasi_desktop(): void
    {
        $html = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2025])
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('aplikasi-desktop'), $html);
        $this->assertStringContainsString('Unduh Aplikasi Desktop', $html);
        $this->assertStringNotContainsString('action="'.route('tarik-arkas.tarik').'"', $html,
            'Website pusat tidak boleh memasang form penarikan — unduh aplikasi di laptop via halaman Aplikasi Desktop.');
    }

    /**
     * Kontrak sheet: agen wajib mengekspor semua sheet yang dibaca
     * ArkasImportService. Regresi nyata — agen sempat tidak mengekspor
     * `RAPBS_Periode`, sehingga transaksi masuk tanpa kode kegiatan dan RKAS
     * tanpa volume per tahap.
     */
    public function test_agen_mengekspor_semua_sheet_yang_dibutuhkan_impor(): void
    {
        $sumber = (string) file_get_contents(base_path('agen-arkas/tarik-arkas.js'));

        preg_match_all("/isi\\('([A-Za-z_]+)'/", $sumber, $m);
        $dibuat = array_values(array_unique($m[1]));

        $wajib = ['Sekolah', 'Anggaran', 'RAPBS', 'RAPBS_Periode', 'KAS_Umum', 'Nota', 'Pajak_NTPN', 'Ref_Kode', 'Ref_Rekening'];
        $hilang = array_values(array_diff($wajib, $dibuat));

        $this->assertSame([], $hilang,
            'Agen tidak mengekspor sheet yang dibutuhkan ArkasImportService: '.implode(', ', $hilang));

        // Semua sheet yang dibaca importer harus jelas statusnya: diekspor agen,
        // atau terdaftar sebagai celah yang sudah diketahui (docs/AUDIT-TARIK-ARKAS.md).
        // Bila importer mulai membaca sheet baru, tes ini gagal supaya celahnya
        // tidak bertambah tanpa disadari.
        $layanan = (string) file_get_contents(app_path('Services/ArkasImportService.php'));
        preg_match_all("/sheetAssoc\(\$wb, '([A-Za-z_]+)'/", $layanan, $n);
        $dibaca = array_values(array_unique($n[1]));

        // Celah yang diketahui: belum diekspor agen karena nama tabelnya di
        // arkas.db belum terverifikasi. Penjab = penandatangan (isi manual bila
        // kosong), Salur & Aktivasi_BKU = info silang baca-saja.
        $celahDikenal = ['Penjab', 'Salur', 'Aktivasi_BKU'];

        $takTerduga = array_values(array_diff(array_diff($dibaca, $dibuat), $celahDikenal));
        $this->assertSame([], $takTerduga,
            'Sheet yang dibaca importer tetapi tidak diekspor agen dan belum terdaftar sebagai celah: '
            .implode(', ', $takTerduga));
    }

    public function test_agen_melaporkan_versinya(): void
    {
        $sumber = (string) file_get_contents(base_path('agen-arkas/tarik-arkas.js'));

        $this->assertMatchesRegularExpression("/const AGEN_VERSI = '[0-9.]+'/", $sumber);
        $this->assertStringContainsString('agen: AGEN_VERSI', $sumber,
            'Versi agen harus dilaporkan lewat info/lokal --json supaya diagnosa bisa membedakan agen lama.');
    }

    public function test_diagnosa_arkas_hanya_untuk_localhost(): void
    {
        $this->get('/_arkas')->assertOk()->assertSee('Diagnosa Tarik Data ARKAS');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/_arkas')
            ->assertNotFound();
    }

    public function test_status_lengkap_di_luar_portabel(): void
    {
        $s = TarikArkasService::statusLengkap(2025, true);

        $this->assertFalse($s['portable']);
        $this->assertFalse($s['tersedia']);
        $this->assertNull($s['info'], 'Di luar aplikasi portabel tidak boleh menjalankan agen.');
        $this->assertNull($s['uji'], 'Uji tarik hanya berjalan bila agen & database tersedia.');
        $this->assertSame(2025, $s['tahun']);
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
            ->assertSessionHas('error', 'Penarikan data ARKAS hanya tersedia di aplikasi portabel di laptop sekolah. Di website pusat, unduh aplikasi desktop dari halaman Aplikasi Desktop atau unggah Excel manual di halaman Impor ARKAS.');
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
        $appdataSebelumnya = getenv('APPDATA');
        putenv('APPDATA=C:\\Users\\PenggunaUji\\AppData\\Roaming');

        try {
            $path = TarikArkasService::pathDb();
            $this->assertStringEndsWith('\\Arkas\\arkas.db', $path);
        } finally {
            $appdataSebelumnya === false
                ? putenv('APPDATA')
                : putenv('APPDATA='.$appdataSebelumnya);
        }
    }
}
