<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keamanan token API SiapBOSP:
 * - Token disimpan ter-hash (sha256), bukan plaintext.
 * - Autentikasi hanya lewat header Authorization: Bearer.
 * - Query string ?api_token= sudah tidak didukung (anti bocor log/referer).
 * - Token baru ditampilkan satu kali saat pembuatan.
 */
class ApiTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PLAINTEXT = 'token-plaintext-rahasia-abc123';

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $sekolah = School::create([
            'npsn' => '90000021', 'nama' => 'SD Negeri Token Tes', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->operator = User::create([
            'name' => 'Operator Token', 'email' => 'operator.token@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $sekolah->id,
            'api_token' => hash('sha256', self::PLAINTEXT),
        ]);
    }

    public function test_token_disimpan_ter_hash_bukan_plaintext(): void
    {
        $tersimpan = User::find($this->operator->id)->api_token;

        $this->assertSame(hash('sha256', self::PLAINTEXT), $tersimpan);
        $this->assertNotSame(self::PLAINTEXT, $tersimpan);
    }

    public function test_bearer_token_valid_diterima(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . self::PLAINTEXT)
            ->getJson('/api/v1/profil')
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    public function test_token_di_query_string_ditolak_401(): void
    {
        $this->getJson('/api/v1/profil?api_token=' . self::PLAINTEXT)
            ->assertStatus(401)
            ->assertJsonPath('ok', false);
    }

    public function test_hash_plaintext_dari_query_string_tidak_bisa_dipakai(): void
    {
        // Bahkan mengirim nilai hash-nya sendiri lewat query string tetap ditolak.
        $this->getJson('/api/v1/profil?api_token=' . hash('sha256', self::PLAINTEXT))
            ->assertStatus(401);
    }

    public function test_buat_ulang_token_menyimpan_hash_dan_menampilkan_plain_text_sekali(): void
    {
        $this->actingAs($this->operator)
            ->post('/api-token')
            ->assertSessionHas('token_baru');

        $baru = session('token_baru');
        $this->assertNotEmpty($baru);
        $this->assertNotSame(self::PLAINTEXT, $baru);

        // Database menyimpan hash dari token baru — bukan plaintextnya.
        $tersimpan = User::find($this->operator->id)->api_token;
        $this->assertSame(hash('sha256', $baru), $tersimpan);
        $this->assertNotSame($baru, $tersimpan);

        // Token lama langsung mati; token baru langsung berlaku.
        $this->withHeader('Authorization', 'Bearer ' . self::PLAINTEXT)
            ->getJson('/api/v1/profil')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer ' . $baru)
            ->getJson('/api/v1/profil')->assertStatus(200);
    }
}
