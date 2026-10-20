<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\School;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sync lokal → pusat (Tahap C1, docs/SINKRON-PUSAT.md).
 */
class SyncPushTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolahA;

    private School $sekolahB;

    private User $operatorA;

    private function authA()
    {
        return $this->withHeader('Authorization', 'Bearer token-a');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sekolahA = School::create([
            'npsn' => '90000001', 'nama' => 'SD A', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->sekolahB = School::create([
            'npsn' => '90000002', 'nama' => 'SD B', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->operatorA = User::create([
            'name' => 'Op A', 'email' => 'a@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolahA->id,
            'api_token' => hash('sha256', 'token-a'),
        ]);
        User::create([
            'name' => 'Op B', 'email' => 'b@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolahB->id,
            'api_token' => hash('sha256', 'token-b'),
        ]);
        BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'H', 'tipe' => 'belanja']);
        RekeningPajak::firstOrCreate(['kode' => 'pph21'], ['nama' => 'PPh 21']);
        for ($i = 1; $i <= 5; $i++) {
            Stage::create(['tahun' => 2025, 'nama' => "Tahap $i", 'urutan' => $i, 'is_open' => true]);
        }
    }

    private function belanja(string $kunci, int $total = 5000000): array
    {
        return ['kunci' => $kunci, 'type' => 'belanja', 'tanggal' => '2025-02-10',
            'stage' => 1, 'metode' => 'tunai', 'keterangan' => 'Belanja ATK lokal',
            'total' => $total, 'bos' => 'reguler',
            'rincian' => [['rekening' => '5.1.02.01.01.0001', 'uraian' => 'ATK', 'nilai' => $total]],
            'pajak' => [['jenis' => 'pph21', 'nilai' => 100000, 'kode_billing' => 'B1']]];
    }

    public function test_dorong_baru_lalu_idempoten(): void
    {
        $r1 = $this->authA()->postJson('/api/v1/sync-push', [
            'tahun' => 2025, 'perangkat' => 'laptop-bendahara',
            'data' => ['transaksi' => [$this->belanja('lokal:uuid-1')]],
        ]);
        $r1->assertOk()->assertJsonPath('ok', true)->assertJsonPath('diterima', 1);
        $this->assertSame(1, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->count());
        $bku = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->first()->no_bku;

        $r2 = $this->authA()->postJson('/api/v1/sync-push', [
            'tahun' => 2025, 'data' => ['transaksi' => [$this->belanja('lokal:uuid-1')]],
        ]);
        $r2->assertOk()->assertJsonPath('diperbarui', 1)->assertJsonPath('diterima', 0);
        $this->assertSame(1, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->count());
        $this->assertSame($bku, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->first()->no_bku);
    }

    public function test_isolasi_antar_sekolah(): void
    {
        $this->authA()->postJson('/api/v1/sync-push', [
            'tahun' => 2025, 'data' => ['transaksi' => [$this->belanja('lokal:uuid-1')]],
        ])->assertOk();
        $this->assertSame(0, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahB->id)->count());
        $this->withHeader('Authorization', 'Bearer token-b')
            ->getJson('/api/v1/transaksi?tahun=2025&per_page=50')
            ->assertOk()->assertJsonCount(0, 'transaksi.data');
    }

    public function test_tolak_periode_tutup_buku(): void
    {
        $this->actingAs($this->operatorA);
        TutupBuku::create(['tahun' => 2025,
            'stage_id' => Stage::where('tahun', 2025)->where('urutan', 2)->firstOrFail()->id]);
        $b = $this->belanja('lokal:uuid-2');
        $b['tanggal'] = '2025-05-01';
        $b['stage'] = 2;
        $r = $this->authA()->postJson('/api/v1/sync-push', ['tahun' => 2025, 'data' => ['transaksi' => [$b]]]);
        $r->assertOk()->assertJsonPath('diterima', 0);
        $this->assertSame('lokal:uuid-2', $r->json('ditolak.0.kunci'));
        $this->assertSame(0, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->count());
    }

    public function test_tolak_kode_asing_dan_tanpa_token(): void
    {
        $b = $this->belanja('lokal:uuid-3');
        $b['rincian'][0]['rekening'] = '9.9.99.99.9999';
        $r = $this->authA()->postJson('/api/v1/sync-push', ['tahun' => 2025, 'data' => ['transaksi' => [$b]]]);
        $r->assertOk()->assertJsonPath('diterima', 0);
        $this->assertSame('lokal:uuid-3', $r->json('ditolak.0.kunci'));
        $this->withHeader('Authorization', 'Bearer token-salah')
            ->postJson('/api/v1/sync-push', ['tahun' => 2025, 'data' => ['transaksi' => [$this->belanja('lokal:uuid-9')]]])
            ->assertStatus(401);
    }

    public function test_pajak_sudah_disetor_dilindungi(): void
    {
        $this->authA()->postJson('/api/v1/sync-push', [
            'tahun' => 2025, 'data' => ['transaksi' => [$this->belanja('lokal:uuid-4')]],
        ])->assertOk();
        $trx = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->where('arkas_id', 'lokal:uuid-4')->firstOrFail();
        $trx->taxes()->first()->update(['status' => 'sudah', 'ntpn' => 'N1']);
        $b = $this->belanja('lokal:uuid-4');
        $b['pajak'][0]['nilai'] = 999999;
        $r = $this->authA()->postJson('/api/v1/sync-push', ['tahun' => 2025, 'data' => ['transaksi' => [$b]]]);
        $r->assertOk();
        $this->assertNotEmpty($r->json('peringatan'));
        $this->assertSame(100000, (int) $trx->taxes()->first()->fresh()->nilai);
        $this->assertSame(1, KasTax::whereHas('transaksi', fn ($q) => $q
            ->where('school_id', $this->sekolahA->id)->where('arkas_id', 'lokal:uuid-4'))->count());
    }

    public function test_referensi_memuat_master(): void
    {
        $r = $this->authA()->getJson('/api/v1/sync-referensi?tahun=2025')->assertOk();
        $r->assertJsonPath('ok', true);
        $this->assertGreaterThanOrEqual(44, count($r->json('rekenings')));
        $this->assertCount(5, $r->json('stages'));
        $this->assertNotEmpty($r->json('versi_master'));
    }
}
