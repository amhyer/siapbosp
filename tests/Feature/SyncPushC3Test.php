<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\LpjSubmission;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sync C3: RKAS + tandatangan + tutup/LPJ + lampiran (docs/SINKRON-PUSAT.md).
 */
class SyncPushC3Test extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private function auth()
    {
        return $this->withHeader('Authorization', 'Bearer token-a');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sekolah = School::create([
            'npsn' => '90000001', 'nama' => 'SD A', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        User::create(['name' => 'Op', 'email' => 'a@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
            'api_token' => hash('sha256', 'token-a')]);
        BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'H', 'tipe' => 'belanja']);
        for ($i = 1; $i <= 5; $i++) {
            Stage::create(['tahun' => 2025, 'nama' => "Tahap $i", 'urutan' => $i, 'is_open' => true]);
        }
    }

    private function dorong(array $data)
    {
        return $this->auth()->postJson('/api/v1/sync-push',
            ['tahun' => 2025, 'data' => $data]);
    }

    private function rkasDetail(string $kunci = 'rk:1'): array
    {
        return ['kunci' => $kunci, 'rekening' => '5.1.02.01.01.0001', 'uraian' => 'ATK',
            'satuan' => 'paket', 'banyak' => 2, 'harga' => 50000, 'jumlah' => 100000, 'sumber' => 'BOS Reguler'];
    }

    public function test_rkas_masuk_draft_dan_idempoten(): void
    {
        $r1 = $this->dorong(['rkas' => ['details' => [$this->rkasDetail()]]])->assertOk();
        $this->assertSame(1, $r1->json('rkas.dibuat'));
        $this->assertSame(1, RkasHeader::where('school_id', $this->sekolah->id)->where('tahun', 2025)->count());
        $r2 = $this->dorong(['rkas' => ['details' => [$this->rkasDetail()]]])->assertOk();
        $this->assertSame(1, $r2->json('rkas.diperbarui'));
        $this->assertSame(1, RkasHeader::where('school_id', $this->sekolah->id)->where('tahun', 2025)
            ->first()->details()->count());
    }

    public function test_rkas_ditolak_bila_sudah_disetujui(): void
    {
        RkasHeader::create(['school_id' => $this->sekolah->id, 'tahun' => 2025, 'revisi_ke' => 1, 'status' => 'disetujui']);
        $r = $this->dorong(['rkas' => ['details' => [$this->rkasDetail()]]])->assertOk();
        $this->assertNotEmpty($r->json('rkas.ditolak'));
        $this->assertSame(0, RkasHeader::where('school_id', $this->sekolah->id)->where('tahun', 2025)
            ->where('status', 'draft')->count());
    }

    public function test_tandatangan_tanpa_duplikat(): void
    {
        $t = [['nama' => 'Budi', 'jabatan' => 'Bendahara', 'nip' => '123']];
        $this->dorong(['tandatangan' => $t])->assertOk();
        $this->dorong(['tandatangan' => $t])->assertOk();
        $this->assertSame(1, Penandatangan::where('school_id', $this->sekolah->id)->count());
    }

    public function test_tutup_lalu_kirim_lpj(): void
    {
        $r = $this->dorong(['tutup' => [1]])->assertOk();
        $this->assertSame([1], $r->json('tutup.ditutup'));
        $this->assertTrue(TutupBuku::terkunci($this->sekolah->id, 2025,
            Stage::where('tahun', 2025)->where('urutan', 1)->first()->id));
        // LPJ tanpa tutup buku → dilewati.
        $r2 = $this->dorong(['lpj_kumpulkan' => [2]])->assertOk();
        $this->assertSame([], $r2->json('lpj.dikirim'));
        // LPJ tahap terkunci → terkirim; kirim ulang → dilewati.
        $r3 = $this->dorong(['lpj_kumpulkan' => [1]])->assertOk();
        $this->assertSame([1], $r3->json('lpj.dikirim'));
        $r4 = $this->dorong(['lpj_kumpulkan' => [1]])->assertOk();
        $this->assertSame([], $r4->json('lpj.dikirim'));
        $this->assertSame(1, LpjSubmission::where('school_id', $this->sekolah->id)->count());
    }

    public function test_lampiran_tersimpan_dan_dedup(): void
    {
        $trx = ['kunci' => 'lokal:lamp-1', 'type' => 'belanja', 'tanggal' => '2025-02-10',
            'stage' => 1, 'metode' => 'tunai', 'keterangan' => 'ATK', 'total' => 100000,
            'rincian' => [['rekening' => '5.1.02.01.01.0001', 'uraian' => 'ATK', 'nilai' => 100000]],
            'lampiran' => [['nama' => 'nota.pdf', 'base64' => base64_encode('isi-nota')]]];
        $this->dorong(['transaksi' => [$trx]])->assertOk();
        $t = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolah->id)->where('arkas_id', 'lokal:lamp-1')->firstOrFail();
        $this->assertSame(1, $t->attachments()->count());
        $this->assertSame('isi-nota', Storage::get($t->attachments()->first()->path));
        // Dorong ulang + lampiran rusak → 1 valid didedup, 1 ditolak.
        $trx['lampiran'][] = ['nama' => 'rusak.pdf', 'base64' => '!!!bukan-base64!!!'];
        $r = $this->dorong(['transaksi' => [$trx]])->assertOk();
        $this->assertSame(1, $t->attachments()->count());
        $this->assertNotEmpty($r->json('ditolak'));
    }
}
