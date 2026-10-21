<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Perintah dorong lokal → pusat (Tahap C2).
 */
class SyncPushCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $conf;

    protected function setUp(): void
    {
        parent::setUp();
        $s = School::create([
            'npsn' => '90000001', 'nama' => 'SD A', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        User::create(['name' => 'Op', 'email' => 'op@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);
        BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        $rek = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'H', 'tipe' => 'belanja']);
        Stage::create(['tahun' => 2025, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $t = KasTransaction::create(['school_id' => $s->id, 'tahun' => 2025, 'type' => 'belanja',
            'no_bku' => 1, 'tanggal' => '2025-02-10', 'metode_bayar' => 'tunai',
            'keterangan' => 'ATK', 'total' => 1000000, 'arkas_id' => 'kas:UJI-1']);
        $t->details()->create(['rekening_id' => $rek->id, 'uraian' => 'ATK', 'nilai' => 1000000]);
        $this->conf = base_path('launcher-windows/akun-sekolah.conf');
        file_put_contents($this->conf, "NPSN=90000001\nPUSAT_URL=https://pusat.test\nTOKEN=tok-uji\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->conf);
        parent::tearDown();
    }

    public function test_dorong_mengirim_perubahan_dan_mencatat_waktu(): void
    {
        Http::fake(['*' => Http::response(
            ['ok' => true, 'tahun' => 2025, 'diterima' => 1, 'diperbarui' => 0, 'ditolak' => [], 'peringatan' => []], 200)]);
        $this->artisan('siapbos:sync-push', ['--tahun' => 2025])->assertExitCode(0);
        Http::assertSent(function ($req) {
            $paket = $req->data();
            return str_ends_with($req->url(), '/api/v1/sync-push')
                && $paket['tahun'] === 2025
                && $paket['data']['transaksi'][0]['kunci'] === 'kas:UJI-1';
        });
        $this->assertNotNull(DB::table('sync_states')->where('kunci', 'terakhir_dorong')->value('nilai'));
        // Dorongan kedua: tidak ada perubahan → tanpa HTTP.
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $this->artisan('siapbos:sync-push', ['--tahun' => 2025])->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_mode_kering_tanpa_mengirim(): void
    {
        Http::fake();
        $this->artisan('siapbos:sync-push', ['--tahun' => 2025, '--kering' => true])->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_npsn_beda_ditolak(): void
    {
        file_put_contents($this->conf, "NPSN=99999999\nPUSAT_URL=https://pusat.test\nTOKEN=x\n");
        $this->artisan('siapbos:sync-push', ['--tahun' => 2025])->assertExitCode(1);
    }

    public function test_mengirim_rkas_tutup_dan_tandatangan(): void
    {
        $s = \App\Models\School::where('npsn', '90000001')->firstOrFail();
        $h = \App\Models\RkasHeader::create(['school_id' => $s->id, 'tahun' => 2025,
            'revisi_ke' => 1, 'status' => 'draft']);
        $h->details()->create(['rekening_id' => \App\Models\Rekening::where('kode', '5.1.02.01.01.0001')->first()->id,
            'uraian' => 'ATK', 'harga_satuan' => 1000, 'jumlah' => 1000, 'arkas_id' => 'rapbs:UJI-1']);
        \App\Models\Penandatangan::create(['school_id' => $s->id, 'nama' => 'Budi', 'jabatan' => 'Bendahara']);
        \App\Models\TutupBuku::create(['school_id' => $s->id, 'tahun' => 2025,
            'stage_id' => \App\Models\Stage::where('tahun', 2025)->where('urutan', 1)->first()->id]);
        Http::fake(['*' => Http::response(
            ['ok' => true, 'tahun' => 2025, 'diterima' => 0, 'diperbarui' => 0, 'ditolak' => [], 'peringatan' => [],
                'rkas' => ['dibuat' => 0, 'diperbarui' => 0, 'ditolak' => []], 'tandatangan' => 0,
                'tutup' => ['ditutup' => [], 'ditolak' => []], 'lpj' => ['dikirim' => [], 'dilewati' => []]], 200)]);
        $this->artisan('siapbos:sync-push', ['--tahun' => 2025])->assertExitCode(0);
        Http::assertSent(function ($req) {
            $d = $req->data()['data'];
            return isset($d['rkas']['details'][0]['kunci'])
                && $d['tandatangan'][0]['nama'] === 'Budi'
                && $d['tutup'] === [1];
        });
    }
}
