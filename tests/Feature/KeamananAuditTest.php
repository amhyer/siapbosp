<?php

namespace Tests\Feature;

use App\Http\Controllers\PengaturanController;
use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\BosType;
use App\Models\KasDetail;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\User;
use App\Services\ArkasImportService;
use App\Services\JuknisBosp2026Service;
use App\Services\LpjExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi temuan audit keamanan (lihat docs/AUDIT-KEAMANAN.md):
 *
 * - dump basis data penuh tidak boleh diunduh akun sekolah di server pusat;
 * - akun Dinas tidak boleh mengelola akun superadmin (anti eskalasi);
 * - log sistem & riwayat arsip tidak boleh membocorkan data sekolah lain;
 * - akun/sekolah nonaktif langsung kehilangan sesi web maupun token API;
 * - ekspor CSV tidak boleh meneruskan formula (CSV injection);
 * - sekolah tidak boleh menulis pagu sendiri lewat profil.
 */
class KeamananAuditTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolahA;

    private School $sekolahB;

    private User $operatorA;

    private User $dinas;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolahA = School::create([
            'npsn' => '91000001', 'nama' => 'SD Aman Audit', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->sekolahB = School::create([
            'npsn' => '91000002', 'nama' => 'SD Tetangga', 'jenjang' => 'SD',
            'kecamatan' => 'Losari', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->operatorA = User::create([
            'name' => 'Operator A', 'email' => 'op.audit.a@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolahA->id,
            'api_token' => hash('sha256', 'token-audit-a'),
        ]);
        $this->dinas = User::create([
            'name' => 'Admin Dinas', 'email' => 'dinas.audit@tes.test', 'password' => 'password',
            'role' => 'dinas',
        ]);
        $this->superadmin = User::create([
            'name' => 'Super Admin', 'email' => 'super.audit@tes.test', 'password' => 'password',
            'role' => 'superadmin',
        ]);

        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
    }

    public function test_akun_sekolah_tidak_bisa_mengunduh_dump_basis_data_penuh(): void
    {
        $this->actingAs($this->operatorA)->get('/pengaturan/backup/unduh')->assertForbidden();

        // Halaman penjelasan tetap boleh dibuka, tetapi tombol unduh disembunyikan.
        $this->actingAs($this->operatorA)->get('/pengaturan/backup')
            ->assertOk()
            ->assertDontSee('Unduh Backup (.zip)');
    }

    public function test_hanya_superadmin_atau_mode_portabel_yang_boleh_mengunduh_backup(): void
    {
        $this->actingAs($this->operatorA);
        $this->assertFalse(PengaturanController::bolehUnduhBackup());

        $this->actingAs($this->superadmin);
        $this->assertTrue(PengaturanController::bolehUnduhBackup());
    }

    public function test_dinas_tidak_bisa_mereset_sandi_superadmin(): void
    {
        $this->actingAs($this->dinas)
            ->post('/dinas/pengguna/'.$this->superadmin->id.'/reset', [])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $this->superadmin->fresh()->password));
    }

    public function test_dinas_tidak_bisa_mengubah_email_atau_menghapus_superadmin(): void
    {
        $this->actingAs($this->dinas)
            ->put('/dinas/pengguna/'.$this->superadmin->id, [
                'name' => 'Diambil Alih',
                'email' => 'diambil-alih@tes.test',
                'role' => 'dinas',
                'school_id' => null,
            ])->assertForbidden();

        $this->actingAs($this->dinas)
            ->delete('/dinas/pengguna/'.$this->superadmin->id)
            ->assertForbidden();

        $segar = $this->superadmin->fresh();
        $this->assertSame('super.audit@tes.test', $segar->email);
        $this->assertSame('superadmin', $segar->role);
    }

    public function test_impor_pengguna_tidak_bisa_dipakai_eskalasi_ke_superadmin(): void
    {
        $csv = "nama;email;npsn;role;password\n"
            ."Penyusup;penyusup@tes.test;;superadmin;sandiJahat1\n"
            ."Operator Baru;operator.baru@tes.test;91000001;operator;sandiBaru123\n";

        $this->actingAs($this->dinas)->post('/dinas/pengguna/impor', [
            'file' => UploadedFile::fake()->createWithContent('pengguna.csv', $csv),
        ])->assertRedirect();

        // Baris superadmin dilewati ...
        $this->assertNull(User::where('email', 'penyusup@tes.test')->first());
        $this->assertSame(1, User::where('role', 'superadmin')->count());

        // ... sedangkan baris sah tetap dibuat.
        $this->assertNotNull(User::where('email', 'operator.baru@tes.test')->first());
    }

    public function test_rute_auth_saja_ikut_diputus_saat_akun_dinonaktifkan(): void
    {
        // /referensi & /api-token hanya memakai middleware `auth` (tanpa `role`).
        $this->actingAs($this->operatorA)->get('/referensi')->assertOk();

        $this->operatorA->update(['is_active' => false]);

        $this->actingAs($this->operatorA->fresh())
            ->get('/referensi')
            ->assertRedirect('/login');
        $this->assertGuest();

        // Token API tidak boleh dibuat/di-rotasi lagi oleh akun nonaktif.
        $tokenSebelum = $this->operatorA->fresh()->api_token;
        $this->actingAs($this->operatorA->fresh())
            ->post('/api-token')
            ->assertRedirect('/login');
        $this->assertSame($tokenSebelum, $this->operatorA->fresh()->api_token,
            'Token API tidak boleh berubah saat akun nonaktif.');
    }

    public function test_sekolah_nonaktif_memutus_semua_sesi_penggunanya(): void
    {
        $this->actingAs($this->operatorA)->get('/referensi')->assertOk();

        $this->sekolahA->update(['is_active' => false]);

        $this->actingAs($this->operatorA->fresh())
            ->get('/referensi')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_log_sistem_hanya_menampilkan_aktivitas_sekolah_sendiri(): void
    {
        ActivityLog::create([
            'school_id' => $this->sekolahA->id, 'user_id' => $this->operatorA->id,
            'user_name' => 'Operator A', 'aksi' => 'impor_arkas', 'keterangan' => 'IMPOR-SEKOLAH-A',
        ]);
        ActivityLog::create([
            'school_id' => $this->sekolahB->id, 'user_name' => 'Operator B',
            'aksi' => 'impor_arkas', 'keterangan' => 'IMPOR-SEKOLAH-B',
        ]);

        $this->actingAs($this->operatorA)->get('/pengaturan/log')
            ->assertOk()
            ->assertSee('IMPOR-SEKOLAH-A')
            ->assertDontSee('IMPOR-SEKOLAH-B');
    }

    public function test_riwayat_impor_dan_ekspor_tidak_membocorkan_sekolah_lain(): void
    {
        ActivityLog::create([
            'school_id' => $this->sekolahA->id, 'aksi' => 'ekspor', 'keterangan' => 'EKSPOR-A',
        ]);
        ActivityLog::create([
            'school_id' => $this->sekolahB->id, 'aksi' => 'ekspor', 'keterangan' => 'EKSPOR-B',
        ]);

        $this->actingAs($this->operatorA)->get('/arsip/ekspor')
            ->assertOk()->assertSee('EKSPOR-A')->assertDontSee('EKSPOR-B');
        $this->actingAs($this->operatorA)->get('/arsip/impor')
            ->assertOk()->assertDontSee('EKSPOR-B');
    }

    public function test_token_api_akun_nonaktif_ditolak(): void
    {
        $this->operatorA->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer token-audit-a')
            ->getJson('/api/v1/saldo')
            ->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_token_api_sekolah_nonaktif_ditolak(): void
    {
        $this->sekolahA->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer token-audit-a')
            ->getJson('/api/v1/saldo')
            ->assertStatus(403);
    }

    public function test_sesi_akun_yang_dinonaktifkan_langsung_diputus(): void
    {
        $this->actingAs($this->operatorA)->get('/dashboard')->assertOk();

        $this->operatorA->update(['is_active' => false]);

        $this->actingAs($this->operatorA->fresh())
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_reset_sandi_oleh_dinas_mematikan_sesi_lama(): void
    {
        // Sesi operator sudah berjalan dengan hash sandi "lama".
        $hashLama = $this->operatorA->getAuthPassword();
        $this->actingAs($this->operatorA)
            ->withSession(['password_hash_web' => $hashLama])
            ->get('/referensi')->assertOk();

        // Dinas mereset sandi akun tersebut. Feature test berganti identitas
        // pada klien yang sama, jadi simulasikan sesi superadmin terpisah.
        $this->actingAs($this->superadmin)
            ->withSession(['password_hash_web' => (string) $this->superadmin->getAuthPassword()])
            ->post('/dinas/pengguna/'.$this->operatorA->id.'/reset', ['password' => 'SandiBaru123'])
            ->assertRedirect();

        $this->assertNotSame($hashLama, (string) $this->operatorA->fresh()->getAuthPassword());

        // Sesi lama (mis. milik penyerang) tidak boleh hidup lagi.
        $this->actingAs($this->operatorA->fresh())
            ->withSession(['password_hash_web' => $hashLama])
            ->get('/referensi')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_menonaktifkan_akun_mencabut_token_api(): void
    {
        $this->assertNotNull($this->operatorA->api_token);

        $this->actingAs($this->dinas)
            ->post('/dinas/pengguna/'.$this->operatorA->id.'/toggle')
            ->assertRedirect();

        $this->assertNull($this->operatorA->fresh()->api_token);

        // Token lama tidak lagi dikenali (401), bukan hanya diblokir per akun.
        $this->withHeaders(['Authorization' => 'Bearer token-audit-a'])
            ->getJson('/api/v1/saldo')->assertStatus(401);
    }

    public function test_ekspor_bku_menetralkan_formula_csv_injection(): void
    {
        KasTransaction::create([
            'school_id' => $this->sekolahA->id, 'tahun' => (int) now()->year, 'type' => 'pendapatan',
            'no_bku' => 1, 'tanggal' => now()->toDateString(), 'metode_bayar' => 'transfer',
            'keterangan' => '=HYPERLINK("http://jahat.test","klik")', 'total' => 1000000,
        ]);

        $res = $this->actingAs($this->operatorA)
            ->get('/pembukuan/bku.csv?bulan='.now()->format('Y-m'));
        $res->assertOk();

        $isi = $res->streamedContent();
        $this->assertStringContainsString("\"'=HYPERLINK", $isi);
    }

    public function test_tinjauan_dinas_tidak_memakai_estimasi_pagu_isi_sekolah(): void
    {
        // Sekolah mengisi Dapodik dengan angka besar (self-report) ...
        $this->sekolahA->update(['jumlah_siswa' => 900, 'satuan_biaya_bosp' => 9_000_000]);
        $tahun = (int) now()->year;

        // ... halaman sekolah boleh memakainya sebagai rujukan ...
        $this->assertSame(8_100_000_000, JuknisBosp2026Service::paguSumberDana($this->sekolahA, $tahun, 'BOS Reguler'));

        // ... tetapi jalur Dinas (pakaiEstimasi=false) hanya percaya alokasi resmi.
        $this->assertSame(0, JuknisBosp2026Service::paguSumberDana($this->sekolahA, $tahun, 'BOS Reguler', false));

        // Setelah Dinas menetapkan alokasi, nilainya yang dipakai.
        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        Allocation::withoutGlobalScope('school')->create([
            'school_id' => $this->sekolahA->id, 'tahun' => $tahun,
            'bos_type_id' => $bos->id, 'stage_id' => null, 'nilai' => 150_000_000,
        ]);
        $this->assertSame(150_000_000, JuknisBosp2026Service::paguSumberDana($this->sekolahA, $tahun, 'BOS Reguler', false));
    }

    public function test_impor_arkas_sekolah_tidak_boleh_menetapkan_pagu_resmi(): void
    {
        // Berkas ARKAS diunggah sekolah → angkanya tidak boleh jadi pagu resmi.
        $this->actingAs($this->operatorA);
        $this->assertFalse(ArkasImportService::bolehTetapkanPagu());

        $this->actingAs($this->dinas);
        $this->assertTrue(ArkasImportService::bolehTetapkanPagu());
    }

    public function test_nama_berkas_ekspor_tidak_bisa_disusupi_baris_baru(): void
    {
        $sekolah = $this->sekolahA;
        $sekolah->nama = "SD A\r\nX-Injected: 1";

        // $d[1] = objek tahap, $d[10] = tahun (struktur dataLpj()).
        $nama = LpjExportService::namaBerkas([1 => (object) ['nama' => "Tahap I\r\nX-Evil: 2"], 10 => 2026], $sekolah, 'xlsx');

        foreach (["\r", "\n", '"', ';'] as $haram) {
            $this->assertStringNotContainsString($haram, $nama, "Nama berkas memuat karakter berbahaya: {$haram}");
        }
        $this->assertStringStartsWith('LPJ_', $nama);
    }

    public function test_tidak_bisa_memetakan_rincian_belanja_sekolah_lain_lewat_hpp(): void
    {
        $rekening = Rekening::create(['kode' => '5.1.02.01', 'nama' => 'Alat Tulis Kantor', 'tipe' => 'belanja']);

        $trxB = KasTransaction::create([
            'school_id' => $this->sekolahB->id, 'tahun' => (int) now()->year, 'type' => 'belanja',
            'no_bku' => 1, 'tanggal' => now()->toDateString(), 'metode_bayar' => 'tunai',
            'keterangan' => 'Belanja Sekolah B', 'total' => 1000,
        ]);
        $detailB = KasDetail::create([
            'kas_transaction_id' => $trxB->id, 'rekening_id' => $rekening->id,
            'uraian' => 'Kertas Sekolah B', 'nilai' => 1000,
        ]);
        $sumberBSebelum = $detailB->fresh()->sumber;

        $trxA = KasTransaction::create([
            'school_id' => $this->sekolahA->id, 'tahun' => (int) now()->year, 'type' => 'belanja',
            'no_bku' => 1, 'tanggal' => now()->toDateString(), 'metode_bayar' => 'tunai',
            'keterangan' => 'Belanja Sekolah A', 'total' => 1000,
        ]);
        $detailA = KasDetail::create([
            'kas_transaction_id' => $trxA->id, 'rekening_id' => $rekening->id,
            'uraian' => 'Kertas Sekolah A', 'nilai' => 1000,
        ]);

        $headerB = RkasHeader::create([
            'school_id' => $this->sekolahB->id, 'tahun' => (int) now()->year,
            'revisi_ke' => 1, 'status' => 'draft',
        ]);
        $rkasB = RkasDetail::create([
            'rkas_header_id' => $headerB->id, 'rekening_id' => $rekening->id,
            'uraian' => 'RKAS Sekolah B', 'satuan' => 'rim', 'banyak' => 1,
            'harga_satuan' => 1000, 'jumlah' => 1000,
        ]);

        $this->actingAs($this->operatorA)->post('/hpp', ['peta' => [
            $detailB->id => ['rkas_detail_id' => $rkasB->id, 'sumber' => 'siplah'],
            $detailA->id => ['rkas_detail_id' => null, 'sumber' => 'siplah'],
        ]])->assertRedirect();

        // Rincian belanja sekolah B tidak boleh berubah ...
        $this->assertNull($detailB->fresh()->rkas_detail_id);
        $this->assertSame($sumberBSebelum, $detailB->fresh()->sumber);

        // ... sedangkan milik sendiri tetap tersimpan.
        $this->assertSame('siplah', $detailA->fresh()->sumber);
    }

    public function test_sekolah_tidak_bisa_menulis_pagu_lewat_profil(): void
    {
        $this->actingAs($this->operatorA)->put('/utility/profil', [
            'nama' => 'SD Aman Audit',
            'jenjang' => 'SD',
            'kecamatan' => 'Mamajang Baru',
            'jumlah_siswa' => 500,
            'satuan_biaya_bosp' => 2000000,
            'sinkron_pagu' => 1,
        ])->assertRedirect();

        $segar = $this->sekolahA->fresh();

        // Identitas & data Dapodik (self-report) tetap boleh diperbarui ...
        $this->assertSame('Mamajang Baru', $segar->kecamatan);
        $this->assertSame(500, (int) $segar->jumlah_siswa);
        $this->assertSame(2000000, (int) $segar->satuan_biaya_bosp);

        // ... tetapi tabel pagu resmi (allocations) tidak boleh disentuh sekolah.
        $this->assertSame(0, Allocation::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->count());
    }
}
