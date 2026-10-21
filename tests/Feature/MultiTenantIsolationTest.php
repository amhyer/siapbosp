<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian isolasi multi-tenant SiapBOSP.
 *
 * Setiap pengguna sekolah hanya boleh melihat data sekolahnya sendiri,
 * baik lewat query Eloquent (global scope), route web, maupun API token.
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolahA;
    private School $sekolahB;
    private User $operatorA;
    private User $operatorB;
    private User $dinas;
    private KasTransaction $trx;
    private KasTransaction $trxB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolahA = School::create([
            'npsn' => '90000001', 'nama' => 'SD Negeri A Mamajang', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->sekolahB = School::create([
            'npsn' => '90000002', 'nama' => 'SD Negeri B Losari', 'jenjang' => 'SD',
            'kecamatan' => 'Ujung Pandang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->operatorA = User::create([
            'name' => 'Operator A', 'email' => 'operator.a@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolahA->id,
            'api_token' => hash('sha256', 'token-rahasia-sekolah-a'),
        ]);
        $this->operatorB = User::create([
            'name' => 'Operator B', 'email' => 'operator.b@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolahB->id,
            'api_token' => hash('sha256', 'token-rahasia-sekolah-b'),
        ]);
        $this->dinas = User::create([
            'name' => 'Admin Dinas', 'email' => 'dinas@tes.test', 'password' => 'password',
            'role' => 'dinas', 'school_id' => null,
            'api_token' => hash('sha256', 'token-rahasia-dinas'),
        ]);

        $bosType = BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        Allocation::create(['school_id' => $this->sekolahA->id, 'tahun' => (int) now()->year, 'bos_type_id' => $bosType->id, 'nilai' => 150000000]);
        Allocation::create(['school_id' => $this->sekolahB->id, 'tahun' => (int) now()->year, 'bos_type_id' => $bosType->id, 'nilai' => 88500000]);

        // Dibuat tanpa auth (di setUp), jadi school_id diisi eksplisit.
        $this->trx = KasTransaction::create([
            'school_id' => $this->sekolahA->id, 'tahun' => (int) now()->year, 'type' => 'pendapatan',
            'no_bku' => 1, 'tanggal' => now()->toDateString(), 'metode_bayar' => 'transfer',
            'keterangan' => 'Pencairan Tahap 1 Sekolah A', 'total' => 60000000,
        ]);
        $this->trxB = KasTransaction::create([
            'school_id' => $this->sekolahB->id, 'tahun' => (int) now()->year, 'type' => 'pendapatan',
            'no_bku' => 1, 'tanggal' => now()->toDateString(), 'metode_bayar' => 'transfer',
            'keterangan' => 'Pencairan Tahap 1 Sekolah B', 'total' => 30000000,
        ]);
    }

    public function test_global_scope_hanya_mengembalikan_transaksi_sekolah_sendiri(): void
    {
        $this->actingAs($this->operatorA);
        $this->assertSame([$this->trx->id], KasTransaction::pluck('id')->all());

        $this->actingAs($this->operatorB);
        $this->assertSame([$this->trxB->id], KasTransaction::pluck('id')->all());
    }

    public function test_membuat_transaksi_otomatis_mengisi_school_id(): void
    {
        $this->actingAs($this->operatorA);

        $baru = KasTransaction::create([
            'tahun' => (int) now()->year, 'type' => 'pendapatan', 'tanggal' => now()->toDateString(),
            'keterangan' => 'Pendapatan baru', 'total' => 1000,
        ]);

        $this->assertSame($this->sekolahA->id, (int) $baru->school_id);
        // 2 transaksi bawaan setUp + 1 transaksi baru = 3.
        $this->assertSame(2, KasTransaction::count());
        $this->assertSame(3, KasTransaction::withoutGlobalScope('school')->count());
    }

    public function test_user_dinas_bisa_melihat_seluruh_sekolah(): void
    {
        $this->actingAs($this->dinas);

        // Global scope tidak menyaring ketika user tidak terikat sekolah tertentu.
        $this->assertSame(2, KasTransaction::count());
        $this->assertSame(2, School::count());
    }

    public function test_operator_ditolak_masuk_panel_dinas(): void
    {
        $this->actingAs($this->operatorA)
            ->get('/dinas')
            ->assertStatus(403);
    }

    public function test_transaksi_lintas_sekolah_tidak_bisa_dibuka_via_route(): void
    {
        // Route binding memakai global scope → transaksi sekolah B 404 untuk operator A.
        $this->actingAs($this->operatorA)
            ->get('/pengeluaran/belanja/' . $this->trxB->id)
            ->assertStatus(404);

        $this->actingAs($this->operatorA);
        $this->assertNotNull(KasTransaction::find($this->trx->id));
        $this->assertNull(KasTransaction::find($this->trxB->id));
    }

    public function test_bku_csv_hanya_memuat_transaksi_sekolah_sendiri(): void
    {
        $this->actingAs($this->operatorA);

        $response = $this->get('/pembukuan/bku.csv?bulan=' . now()->format('Y-m'));
        $response->assertStatus(200);

        $isi = $response->streamedContent();
        $this->assertStringContainsString('Pencairan Tahap 1 Sekolah A', $isi);
        $this->assertStringNotContainsString('Pencairan Tahap 1 Sekolah B', $isi);
    }

    public function test_api_saldo_hanya_mengembalikan_data_sekolah_pemilik_token(): void
    {
        $this->withHeader('Authorization', 'Bearer token-rahasia-sekolah-a')
            ->getJson('/api/v1/saldo')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('sekolah', 'SD Negeri A Mamajang')
            ->assertJsonPath('pagu', 150000000)
            ->assertJsonPath('dana_cair', 60000000)
            ->assertJsonPath('saldo.total', 60000000);
    }

    public function test_api_transaksi_hanya_mengembalikan_transaksi_sekolah_pemilik_token(): void
    {
        $this->withHeader('Authorization', 'Bearer token-rahasia-sekolah-a')
            ->getJson('/api/v1/transaksi?per_page=50')
            ->assertStatus(200)
            ->assertJsonCount(1, 'transaksi.data')
            ->assertJsonPath('transaksi.data.0.uraian', 'Pencairan Tahap 1 Sekolah A');
    }

    public function test_api_dinas_ditolak_untuk_token_sekolah(): void
    {
        $this->withHeader('Authorization', 'Bearer token-rahasia-sekolah-a')
            ->getJson('/api/v1/dinas/sekolah')
            ->assertStatus(403);
    }

    public function test_api_token_dinas_dapat_mengakses_konsolidasi(): void
    {
        $this->withHeader('Authorization', 'Bearer token-rahasia-dinas')
            ->getJson('/api/v1/dinas/sekolah')
            ->assertStatus(200);
    }

    public function test_api_token_tidak_valid_ditolak_401(): void
    {
        $this->withHeader('Authorization', 'Bearer token-salah')
            ->getJson('/api/v1/saldo')
            ->assertStatus(401)
            ->assertJsonPath('ok', false);
    }

    public function test_api_ping_tanpa_token_tetap_publik(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }
}
