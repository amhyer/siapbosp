<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi SELURUH endpoint API v1 terhadap mekanisme token baru (hash + bearer-only):
 *
 *   GET /api/v1/ping               — publik
 *   GET /api/v1/profil             — semua token
 *   GET /api/v1/tahap              — semua token
 *   GET /api/v1/saldo              — token sekolah saja (dinas → 403)
 *   GET /api/v1/transaksi          — token sekolah saja (dinas → 403)
 *   GET /api/v1/dinas/sekolah      — token dinas saja (sekolah → 403)
 *   GET /api/v1/dinas/konsolidasi  — token dinas saja (sekolah → 403)
 */
class ApiEndpointVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_SEKOLAH = 'token-plaintext-sekolah-xyz789';
    private const TOKEN_DINAS = 'token-plaintext-dinas-xyz789';

    /** Semua endpoint API v1 selain /ping (yang publik). */
    private const ENDPOINT_TERLINDUNGI = [
        '/api/v1/profil',
        '/api/v1/tahap',
        '/api/v1/saldo',
        '/api/v1/transaksi',
        '/api/v1/dinas/sekolah',
        '/api/v1/dinas/konsolidasi',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $sekolah = School::create([
            'npsn' => '90000031', 'nama' => 'SD Negeri Verifikasi Tes', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        User::create([
            'name' => 'Operator Verifikasi', 'email' => 'operator.verif@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $sekolah->id,
            'api_token' => hash('sha256', self::TOKEN_SEKOLAH),
        ]);
        User::create([
            'name' => 'Dinas Verifikasi', 'email' => 'dinas.verif@tes.test', 'password' => 'password',
            'role' => 'dinas', 'school_id' => null,
            'api_token' => hash('sha256', self::TOKEN_DINAS),
        ]);
    }

    private function denganToken(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function test_ping_publik_tanpa_token(): void
    {
        $this->getJson('/api/v1/ping')->assertStatus(200)->assertJsonPath('ok', true);
    }

    public function test_semua_endpoint_terlindungi_menolak_tanpa_token(): void
    {
        foreach (self::ENDPOINT_TERLINDUNGI as $ep) {
            $this->assertSame(401, $this->getJson($ep)->getStatusCode(), "Gagal di {$ep}: tanpa token harus 401");
        }
    }

    public function test_semua_endpoint_menolak_token_di_query_string(): void
    {
        foreach (self::ENDPOINT_TERLINDUNGI as $ep) {
            $res = $this->getJson($ep . '?api_token=' . self::TOKEN_SEKOLAH);
            $this->assertSame(401, $res->getStatusCode(), "Gagal di {$ep}: query string harus 401");
        }
    }

    public function test_token_sekolah_valid_di_setiap_endpoint(): void
    {
        $diperbolehkan = [
            '/api/v1/profil' => 200,
            '/api/v1/tahap' => 200,
            '/api/v1/saldo' => 200,
            '/api/v1/transaksi' => 200,
            '/api/v1/dinas/sekolah' => 403,
            '/api/v1/dinas/konsolidasi' => 403,
        ];
        foreach ($diperbolehkan as $ep => $harapan) {
            $res = $this->denganToken(self::TOKEN_SEKOLAH)->getJson($ep);
            $this->assertSame($harapan, $res->getStatusCode(), "Gagal di {$ep}: diharapkan {$harapan}, dapat {$res->getStatusCode()}");
        }
    }

    public function test_token_dinas_valid_di_setiap_endpoint(): void
    {
        $diperbolehkan = [
            '/api/v1/profil' => 200,
            '/api/v1/tahap' => 200,
            '/api/v1/saldo' => 403,
            '/api/v1/transaksi' => 403,
            '/api/v1/dinas/sekolah' => 200,
            '/api/v1/dinas/konsolidasi' => 200,
        ];
        foreach ($diperbolehkan as $ep => $harapan) {
            $res = $this->denganToken(self::TOKEN_DINAS)->getJson($ep);
            $this->assertSame($harapan, $res->getStatusCode(), "Gagal di {$ep}: diharapkan {$harapan}, dapat {$res->getStatusCode()}");
        }
    }

    public function test_isi_resp_saldo_hanya_milik_sekolah_token(): void
    {
        $this->denganToken(self::TOKEN_SEKOLAH)
            ->getJson('/api/v1/saldo')
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('sekolah', 'SD Negeri Verifikasi Tes');
    }

    public function test_endpoint_dinas_mengembalikan_data_lintas_sekolah(): void
    {
        $this->denganToken(self::TOKEN_DINAS)
            ->getJson('/api/v1/dinas/sekolah')
            ->assertStatus(200)
            ->assertJsonPath('jumlah', 1);
    }
}
