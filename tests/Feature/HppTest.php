<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasDetail;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HPP (Halaman Pemetaan Pembelanjaan): petakan belanja (khususnya SIPLah)
 * ke rekening, kegiatan, dan rincian RKAS; rekomendasi revisi RKAS
 * memperingatkan rekening yang sudah terealisasi.
 */
class HppTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private KasDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000201', 'nama' => 'SD HPP', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->operator = User::create([
            'name' => 'Op', 'email' => 'oph@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
        BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        $rek = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'Honor', 'tipe' => 'belanja']);
        Stage::create(['tahun' => 2025, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $t = KasTransaction::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2025, 'type' => 'belanja',
            'no_bku' => 1, 'tanggal' => '2025-02-10', 'metode_bayar' => 'tunai',
            'keterangan' => 'Belanja ATK SIPLah', 'total' => 5000000,
        ]);
        $this->detail = $t->details()->create([
            'rekening_id' => $rek->id, 'uraian' => 'ATK', 'nilai' => 5000000, 'sumber' => 'siplah',
        ]);
        $h = RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2025,
            'revisi_ke' => 1, 'status' => 'disetujui',
        ]);
        $h->details()->create([
            'rekening_id' => $rek->id, 'uraian' => 'Kegiatan ATK', 'harga_satuan' => 6000000,
            'jumlah' => 6000000, 'arkas_id' => 'rapbs:X',
        ]);
    }

    public function test_halaman_hpp_bisa_dibuka_dan_menyimpan_pemetaan(): void
    {
        session(['tahun' => 2025]);
        $this->actingAs($this->operator)->get('/hpp')->assertOk()->assertSee('SIPLah');

        $rcId = RkasHeader::first()->details()->first()->id;
        $this->actingAs($this->operator)->post('/hpp', [
            'peta' => [$this->detail->id => ['rkas_detail_id' => $rcId, 'sumber' => 'siplah']],
        ])->assertRedirect();
        $this->assertSame($rcId, (int) $this->detail->fresh()->rkas_detail_id);
    }

    public function test_form_revisi_memperingatkan_belanja_siplah(): void
    {
        session(['tahun' => 2025]);
        $res = $this->actingAs($this->operator)->get('/rkas/buat');
        $res->assertOk()
            ->assertSee('Sudah pernah dibelanjakan')
            ->assertSee('SIPLah')
            ->assertSee('Bawa');
    }

    public function test_belanja_store_menyimpan_sumber_siplah(): void
    {
        session(['tahun' => 2025]);
        RekeningPajak::create(['kode' => 'ppn', 'nama' => 'PPN']);
        $bos = BosType::first();
        $this->actingAs($this->operator)->post('/pengeluaran/belanja', [
            'tanggal' => '2025-03-01', 'bos_type_id' => $bos->id,
            'stage_id' => Stage::first()->id, 'metode_bayar' => 'tunai',
            'keterangan' => 'Beli via SIPLah', 'sumber' => 'siplah',
            'rekening_id' => [Rekening::first()->id], 'nilai' => [100000],
        ])->assertRedirect();
        $this->assertSame('siplah', KasDetail::orderByDesc('id')->first()->sumber);
    }
}
