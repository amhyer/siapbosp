<?php

namespace Tests\Feature;

use App\Jobs\ProsesImporArkas;
use App\Models\ActivityLog;
use App\Models\ArkasImportRun;
use App\Models\School;
use App\Models\User;
use App\Services\HapusDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi perbaikan lanjutan R-01 … R-12 (docs/AUDIT-KEAMANAN.md §5).
 */
class PerbaikanLanjutanTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private User $dinas;

    private User $dinas2;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '92000001', 'nama' => 'SD Lanjutan Audit', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->operator = User::create([
            'name' => 'Operator Lanjut', 'email' => 'operator.lanjut@tes.test',
            'password' => 'rahasia123', 'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
        $this->dinas = User::create([
            'name' => 'Dinas Satu', 'email' => 'dinas.satu@tes.test',
            'password' => 'rahasia123', 'role' => 'dinas',
        ]);
        $this->dinas2 = User::create([
            'name' => 'Dinas Dua', 'email' => 'dinas.dua@tes.test',
            'password' => 'rahasia123', 'role' => 'dinas',
        ]);
        $this->superadmin = User::create([
            'name' => 'Super Lanjut', 'email' => 'super.lanjut@tes.test',
            'password' => 'rahasia123', 'role' => 'superadmin',
        ]);
    }

    // ---------------------------------------------------------------- R-01

    public function test_sandi_lemah_ditolak_saat_membuat_akun(): void
    {
        $this->actingAs($this->dinas)->post('/dinas/pengguna', [
            'name' => 'Operator Baru',
            'email' => 'operator.baru@tes.test',
            'password' => 'password',
            'role' => 'operator',
            'school_id' => $this->sekolah->id,
        ])->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', 'operator.baru@tes.test')->first());

        // Sandi kuat diterima, dan akun baru wajib ganti sandi saat login.
        $this->actingAs($this->dinas)->post('/dinas/pengguna', [
            'name' => 'Operator Baru',
            'email' => 'operator.baru@tes.test',
            'password' => 'Makassar2026ku',
            'role' => 'operator',
            'school_id' => $this->sekolah->id,
        ])->assertSessionHasNoErrors();

        $baru = User::where('email', 'operator.baru@tes.test')->firstOrFail();
        $this->assertTrue($baru->must_change_password);
    }

    public function test_impor_pengguna_tidak_lagi_memakai_sandi_bawaan(): void
    {
        $csv = "nama;email;npsn;role;password\n"
            ."Tanpa Sandi;tanpa.sandi@tes.test;92000001;operator;\n"
            ."Sandi Kuat;kuat@tes.test;92000001;kepsek;SekolahJuara9\n";

        $this->actingAs($this->dinas)->post('/dinas/pengguna/impor', [
            'file' => UploadedFile::fake()->createWithContent('pengguna.csv', $csv),
        ])->assertRedirect();

        foreach (['tanpa.sandi@tes.test', 'kuat@tes.test'] as $email) {
            $u = User::where('email', $email)->firstOrFail();
            $this->assertTrue($u->must_change_password, "{$email} harus wajib ganti sandi");
            $this->assertFalse(Hash::check('password', $u->password), "{$email} tidak boleh memakai sandi 'password'");
        }
    }

    public function test_akun_wajib_ganti_sandi_diarahkan_ke_halaman_ganti(): void
    {
        $this->operator->update(['must_change_password' => true]);

        $this->actingAs($this->operator->fresh())->get('/dashboard')->assertRedirect('/ganti-password');
        $this->actingAs($this->operator->fresh())->get('/ganti-password')->assertOk();

        // Ganti sandi (kuat, berbeda dari yang lama) → kewajiban selesai.
        $this->actingAs($this->operator->fresh())->post('/ganti-password', [
            'password_lama' => 'rahasia123',
            'password' => 'MakassarBaru2026',
            'password_confirmation' => 'MakassarBaru2026',
        ])->assertSessionHasNoErrors();

        $segar = $this->operator->fresh();
        $this->assertFalse($segar->must_change_password);
        $this->assertTrue(Hash::check('MakassarBaru2026', $segar->password));
    }

    // ---------------------------------------------------------------- R-02

    public function test_token_api_kedaluwarsa_ditolak(): void
    {
        $this->operator->forceFill([
            'api_token' => hash('sha256', 'token-kedaluwarsa'),
            'api_token_expires_at' => now()->subDay(),
        ])->save();

        $this->withHeader('Authorization', 'Bearer token-kedaluwarsa')
            ->getJson('/api/v1/saldo')
            ->assertStatus(401)
            ->assertJsonPath('ok', false);
    }

    public function test_token_api_bisa_dicabut_dari_profil(): void
    {
        $this->operator->forceFill([
            'api_token' => hash('sha256', 'token-aktif'),
            'api_token_expires_at' => now()->addMonth(),
        ])->save();

        $this->actingAs($this->operator)->post('/api-token/cabut')->assertRedirect();

        $this->assertNull($this->operator->fresh()->api_token);
        $this->withHeader('Authorization', 'Bearer token-aktif')
            ->getJson('/api/v1/saldo')->assertStatus(401);
    }

    public function test_menerbitkan_token_baru_memberi_masa_berlaku(): void
    {
        $this->actingAs($this->operator)->post('/api-token')->assertRedirect();

        $segar = $this->operator->fresh();
        $this->assertNotNull($segar->api_token);
        $this->assertNotNull($segar->api_token_expires_at);
        $this->assertTrue($segar->api_token_expires_at->isFuture());
    }

    // ---------------------------------------------------------------- R-03

    public function test_header_membatasi_bingkai_lintas_situs(): void
    {
        $res = $this->get('/login')->assertOk();

        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $res->headers->get('Content-Security-Policy'));
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // ---------------------------------------------------------------- R-04/R-05

    public function test_pengumuman_dibatasi_laju_pengirimannya(): void
    {
        $this->actingAs($this->dinas);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/dinas/pengumuman', ['judul' => "Info {$i}", 'isi' => 'Isi pengumuman.'])
                ->assertRedirect();
        }

        // Percobaan ke-6 dalam satu jam ditolak pembatas laju (R-04/R-05).
        $this->post('/dinas/pengumuman', ['judul' => 'Info 6', 'isi' => 'Isi pengumuman.'])
            ->assertStatus(429);
    }

    // ---------------------------------------------------------------- R-08

    public function test_sync_states_tidak_ditampilkan_di_server_pusat(): void
    {
        DB::table('sync_states')->insert([
            'kunci' => 'terakhir_dorong', 'nilai' => 'RAHASIA-SINKRON-PERANGKAT',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Test berjalan tanpa `php/php.exe` → bukan aplikasi portabel.
        $this->actingAs($this->operator)->get('/arsip/ekspor')
            ->assertOk()
            ->assertDontSee('RAHASIA-SINKRON-PERANGKAT');
    }

    // ---------------------------------------------------------------- R-09

    public function test_dinas_tidak_bisa_mengelola_akun_dinas_lain(): void
    {
        $this->actingAs($this->dinas)
            ->post('/dinas/pengguna/'.$this->dinas2->id.'/reset', ['password' => 'AmbilAlih2026'])
            ->assertForbidden();

        $this->actingAs($this->dinas)
            ->delete('/dinas/pengguna/'.$this->dinas2->id)
            ->assertForbidden();

        $this->assertNotNull(User::find($this->dinas2->id));
    }

    public function test_dinas_tidak_bisa_membuat_akun_dinas_lewat_impor(): void
    {
        $csv = "nama;email;npsn;role;password\n"
            ."Dinas Baru;dinas.baru@tes.test;;dinas;SandiKuat2026\n";

        $this->actingAs($this->dinas)->post('/dinas/pengguna/impor', [
            'file' => UploadedFile::fake()->createWithContent('pengguna.csv', $csv),
        ])->assertRedirect();

        $this->assertNull(User::where('email', 'dinas.baru@tes.test')->first());

        // Superadmin tetap boleh membuat akun dinas.
        $this->actingAs($this->superadmin)->post('/dinas/pengguna/impor', [
            'file' => UploadedFile::fake()->createWithContent('pengguna.csv', $csv),
        ])->assertRedirect();

        $this->assertNotNull(User::where('email', 'dinas.baru@tes.test')->first());
    }

    // ---------------------------------------------------------------- R-12

    public function test_jejak_audit_tidak_bisa_diubah_atau_dihapus(): void
    {
        $log = ActivityLog::create(['aksi' => 'uji', 'keterangan' => 'baris audit', 'user_name' => 'Tester']);

        try {
            $log->update(['keterangan' => 'diubah']);
            $this->fail('Baris audit seharusnya tidak boleh diubah.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $log->delete();
            $this->fail('Baris audit seharusnya tidak boleh dihapus.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak boleh dihapus', $e->getMessage());
        }

        $this->assertSame('baris audit', ActivityLog::find($log->id)->keterangan);
    }

    public function test_reset_data_sekolah_tidak_menghapus_jejak_audit(): void
    {
        ActivityLog::create([
            'school_id' => $this->sekolah->id, 'aksi' => 'transaksi',
            'keterangan' => 'bukti aktivitas', 'user_name' => 'Operator Lanjut',
        ]);

        $hasil = HapusDataService::hapusSekolah($this->sekolah->id, $this->operator->id, 'Operator Lanjut');

        $this->assertSame(1, $hasil['log']);
        $this->assertSame(1, ActivityLog::where('school_id', $this->sekolah->id)
            ->where('keterangan', 'bukti aktivitas')->count());
    }

    public function test_csp_memakai_nonce_acak_di_script_inline(): void
    {
        $response = $this->get('/login')->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $html = (string) $response->getContent();

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        preg_match("/nonce-([^' ]+)/", $csp, $nonce);
        $this->assertNotEmpty($nonce[1] ?? null);
        $this->assertStringContainsString('nonce="'.$nonce[1].'"', $html);
    }

    public function test_unggahan_arkas_dikirim_ke_antrean_dan_dibatasi_milik_pengunggah(): void
    {
        Queue::fake();
        Storage::fake('local');
        $berkas = UploadedFile::fake()->create(
            'ekspor.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->actingAs($this->operator)->post('/impor-arkas', [
            'berkas' => $berkas,
            'tahun' => 2026,
        ]);

        $impor = ArkasImportRun::firstOrFail();
        $response->assertRedirect(route('impor-arkas.status', $impor));
        $this->assertSame('queued', $impor->status);
        $this->assertSame($this->sekolah->id, $impor->school_id);
        Queue::assertPushed(ProsesImporArkas::class, fn (ProsesImporArkas $job): bool =>
            $job->importRunId === $impor->id && $job->queue === 'imports');
        $this->actingAs($this->operator)->post('/impor-arkas', [
            'berkas' => UploadedFile::fake()->create(
                'ekspor-ulang.xlsx',
                10,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
            'tahun' => 2026,
        ])->assertSessionHas('error');

        $sekolahLain = School::create([
            'npsn' => '92000002', 'nama' => 'SD Tetangga', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $runLain = ArkasImportRun::create([
            'school_id' => $sekolahLain->id, 'user_id' => $this->dinas->id,
            'file_path' => 'imports/arkas/rahasia.xlsx', 'tahun' => 2026, 'status' => 'completed',
        ]);
        $this->actingAs($this->operator)->get(route('impor-arkas.status', $runLain))->assertNotFound();
    }

    public function test_impor_csv_rkas_dibatasi_dan_dikirim_ke_antrean(): void
    {
        Queue::fake();
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent(
            'rkas.csv',
            "kode_rekening;uraian;satuan;jumlah;harga\n5.1.02;ATK;paket;1;100000\n"
        );

        $response = $this->actingAs($this->operator)->post('/rkas-impor', ['berkas' => $file]);
        $run = ArkasImportRun::firstOrFail();

        $response->assertRedirect(route('impor-arkas.status', $run));
        $this->assertSame(ArkasImportRun::JENIS_RKAS_CSV, $run->jenis);
        $this->assertSame('queued', $run->status);
        $this->assertStringEndsWith('.csv', $run->file_path);
        Storage::disk('local')->assertExists($run->file_path);
        Queue::assertPushed(ProsesImporArkas::class);
    }

    public function test_gate_memisahkan_kemampuan_akun_alokasi_master_dan_backup(): void
    {
        $this->assertTrue(Gate::forUser($this->dinas)->allows('manage-user', $this->operator));
        $this->assertFalse(Gate::forUser($this->dinas)->allows('manage-user', $this->superadmin));
        $this->assertFalse(Gate::forUser($this->dinas)->allows('assign-user-role', 'superadmin'));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('assign-user-role', 'superadmin'));
        $this->assertTrue(Gate::forUser($this->dinas)->allows('allocation'));
        $this->assertTrue(Gate::forUser($this->dinas)->allows('master-data'));
        $this->assertFalse(Gate::forUser($this->operator)->allows('backup'));
    }
}
