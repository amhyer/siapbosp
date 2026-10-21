<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Komponen;
use App\Models\LpjSubmission;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Services\TtdGambar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Fitur hasil penggabungan v23: kelola akun, keamanan, master data, pajak triwulan, TTD basah, email. */
class GabungV23Test extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $dinas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sekolah = School::create(['npsn' => '90000002', 'nama' => 'SMP F', 'jenjang' => 'SMP',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->dinas = User::create(['name' => 'Admin Dinas', 'email' => 'dinas@t.t', 'password' => 'rahasia',
            'role' => 'dinas']);
    }

    private function buatOperator(array $tambahan = []): User
    {
        return User::create(array_merge(['name' => 'Op F', 'email' => 'opf@t.t', 'password' => 'rahasia',
            'role' => 'operator', 'school_id' => $this->sekolah->id], $tambahan));
    }

    public function test_akun_nonaktif_tidak_bisa_masuk_dan_tercatat_di_log(): void
    {
        $this->buatOperator(['is_active' => false]);

        $res = $this->post('/login', ['email' => 'opf@t.t', 'password' => 'rahasia']);
        $res->assertRedirect();
        $this->assertGuest();

        $log = ActivityLog::where('aksi', 'auth-gagal')->where('user_name', 'opf@t.t')->latest('id')->first();
        $this->assertNotNull($log, 'Percobaan masuk akun nonaktif harus tercatat.');
    }

    public function test_dinas_bisa_nonaktifkan_dan_aktifkan_akun_sekolah(): void
    {
        $op = $this->buatOperator();

        $res = $this->actingAs($this->dinas)->post("/dinas/pengguna/{$op->id}/toggle");
        $res->assertRedirect();
        $this->assertTrue($op->fresh()->is_active === false);

        $res = $this->post('/login', ['email' => 'opf@t.t', 'password' => 'rahasia']);
        $this->assertGuest();

        $this->actingAs($this->dinas)->post("/dinas/pengguna/{$op->id}/toggle");
        $this->assertTrue($op->fresh()->is_active);
    }

    public function test_reset_sandi_lewati_dinas_membuat_sandi_baru_yang_bisa_dipakai(): void
    {
        $op = $this->buatOperator();

        $res = $this->actingAs($this->dinas)->post("/dinas/pengguna/{$op->id}/reset", ['password' => 'sandiBaru1']);
        $res->assertRedirect();

        $res = $this->post('/login', ['email' => 'opf@t.t', 'password' => 'sandiBaru1']);
        $res->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($op->fresh());
    }

    public function test_reset_sandi_kosong_membuat_sandi_acak(): void
    {
        $op = $this->buatOperator();

        $this->actingAs($this->dinas)->post("/dinas/pengguna/{$op->id}/reset", []);
        $this->assertNotSame('rahasia', $op->fresh()->password);
    }

    public function test_dinas_tidak_bisa_menonaktifkan_akun_diri_sendiri(): void
    {
        $res = $this->actingAs($this->dinas)->post("/dinas/pengguna/{$this->dinas->id}/toggle");
        $res->assertSessionHas('error');
        $this->assertTrue($this->dinas->fresh()->is_active);
    }

    public function test_halaman_keamanan_menampilkan_riwayat_login(): void
    {
        ActivityLog::create([
            'user_name' => 'opf@t.t', 'aksi' => 'auth-ok', 'keterangan' => 'Masuk sebagai operator',
            'created_at' => now(),
        ]);

        $res = $this->actingAs($this->dinas)->get('/dinas/keamanan');
        $res->assertOk()->assertSee('MASUK')->assertSee('opf@t.t')->assertSee('Masuk sebagai operator');
    }

    public function test_impor_master_csv_membuat_rekening_dan_komponen(): void
    {
        $csv = "kode_rekening;nama_rekening;kode_komponen;nama_komponen;tipe\n".
            "5.1.02.04.01.0005;Pengadaan buku teks pendamping;02;Pengembangan Perpustakaan;belanja\n".
            "4.3.1.01.01.0003;Dana BOSP;;;pendapatan\n";

        $res = $this->actingAs($this->dinas)->post('/dinas/master/impor', [
            'berkas' => UploadedFile::fake()->createWithContent('master.csv', $csv),
        ]);

        $res->assertRedirect();
        $rek = Rekening::where('kode', '5.1.02.04.01.0005')->first();
        $this->assertNotNull($rek, 'Rekening hasil impor CSV harus ada.');
        $this->assertNotNull($rek->komponen_id);
        $this->assertSame('Pengadaan buku teks pendamping', $rek->nama);
        $this->assertNotNull(Rekening::where('kode', '4.3.1.01.01.0003')->where('tipe', 'pendapatan')->first());
        $this->assertNotNull(Komponen::where('kode', '02')->first());

        // halaman master harus merender relasi komponen↔rekening tanpa error
        $this->actingAs($this->dinas)->get('/dinas/master')
            ->assertOk()
            ->assertSee('Rekening Belanja')
            ->assertSee('Pengembangan Perpustakaan');
    }

    public function test_master_template_mengunduh_csv_contoh(): void
    {
        $res = $this->actingAs($this->dinas)->get('/dinas/master/template');
        $res->assertOk()->assertSee('kode_rekening;nama_rekening');
    }

    public function test_buat_paket_tahap_idempoten(): void
    {
        $res = $this->actingAs($this->dinas)->post('/dinas/tahap/buat', ['tahun' => 2027]);
        $res->assertRedirect();

        $this->assertSame(5, Stage::where('tahun', 2027)->count());
        $this->assertTrue((bool) Stage::where('tahun', 2027)->where('nama', 'Tahap 1')->first()->is_open);

        $res = $this->actingAs($this->dinas)->post('/dinas/tahap/buat', ['tahun' => 2027]);
        $res->assertSessionHas('error', 'Tidak ada tahap baru yang dibuat — tahun 2027 sudah memiliki paket Tahap 1 s/d 5.');

        // Jumlah tahap fleksibel (TA 2026 = dua tahap salur)
        $this->actingAs($this->dinas)->post('/dinas/tahap/buat', ['tahun' => 2028, 'jumlah' => 2]);
        $this->assertSame(2, Stage::where('tahun', 2028)->count());
        $this->assertTrue((bool) Stage::where('tahun', 2028)->where('nama', 'Tahap 2')->first()->is_open === false);
    }

    public function test_kontrol_tahap_punya_kartu_cek_juknis(): void
    {
        Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);

        $res = $this->actingAs($this->dinas)->get('/dinas/tahap');
        $res->assertOk()
            ->assertSee('Cek juknis 50%')
            ->assertSee('SMP F')
            ->assertSee('pagu belum ada');
    }

    public function test_dinas_bisa_meninjau_halaman_lpj_sekolah(): void
    {
        $stage = Stage::create(['tahun' => 2027, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $sub = LpjSubmission::withoutGlobalScopes()->create([
            'school_id' => $this->sekolah->id, 'stage_id' => $stage->id, 'tahun' => 2027,
            'status' => 'dikirim', 'dikirim_oleh' => 'Op F',
        ]);

        $res = $this->actingAs($this->dinas)->get("/dinas/lpj/{$sub->id}");
        $res->assertOk()
            ->assertSee('Tahap 1')
            ->assertSee('Kembali ke LPJ Masuk');
    }

    public function test_halaman_pajak_triwulan_dan_csv_terbuka(): void
    {
        $op = $this->buatOperator();
        $res = $this->actingAs($op)->withSession(['tahun' => now()->year])->get('/laporan/pajak-triwulan');
        $res->assertOk()->assertSee('Triwulan');

        $res = $this->actingAs($op)->withSession(['tahun' => now()->year])->get('/laporan/pajak-triwulan?csv=1');
        $res->assertOk();
        $this->assertStringContainsString('Jenis Pajak', $res->getContent());
    }

    public function test_unggah_ttd_tersimpan_dan_terbaca_sebagai_data_uri(): void
    {
        $p = Penandatangan::create(['jabatan' => 'Kepala Sekolah', 'nama' => 'Bu Sari', 'school_id' => $this->sekolah->id]);

        $res = $this->actingAs($this->buatOperator())->post(
            "/utility/penandatangan/{$p->id}/ttd",
            ['ttd' => UploadedFile::fake()->image('ttd.png', 300, 400)]
        );
        $res->assertRedirect();

        $p->refresh();
        $this->assertNotNull($p->ttd_path);
        $this->assertStringStartsWith('data:image/png;base64,', (string) TtdGambar::dataUri($p->ttd_path));

        $res = $this->delete("/utility/penandatangan/{$p->id}/ttd");
        $res->assertRedirect();
        $this->assertNull($p->fresh()->ttd_path);
    }

    public function test_email_uji_kembali_lapor_status(): void
    {
        $res = $this->actingAs($this->dinas)->post('/dinas/email-uji');
        $res->assertRedirect();

        $res = $this->actingAs($this->dinas)->post('/dinas/email-uji');
        $res->assertSessionHas('ok');
    }
}
