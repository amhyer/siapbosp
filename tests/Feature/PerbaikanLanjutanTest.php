<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\School;
use App\Models\User;
use App\Services\HapusDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
}
