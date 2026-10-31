<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kilat kuning pada baris baru (sisa P1-5).
 *
 * Aksi cepat di lembar BKU membawa `?kembali=bku`. Setelah tersimpan, operator
 * dikembalikan ke BKU dengan `?baru={id}` dan baris itu diberi kelas `.kilat`
 * (animasi CSS, tanpa JavaScript) — padanan ARKAS 4 saat transaksi baru masuk
 * lembar BKU.
 */
class BkuKilatBaruTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private int $bosId;

    private int $rekPendapatan;

    private int $rekBelanja;

    private Stage $tahap;

    protected function setUp(): void
    {
        parent::setUp();

        $sekolah = School::create([
            'npsn' => '90000061', 'nama' => 'SD Kilat', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);

        $this->op = User::create([
            'name' => 'Op Kilat', 'email' => 'op-kilat@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $sekolah->id,
        ]);

        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;
        $this->rekPendapatan = Rekening::firstOrCreate(['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Provinsi', 'tipe' => 'pendapatan'])->id;
        $this->rekBelanja = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'],
            ['nama' => 'Honor Pengelola', 'tipe' => 'belanja'])->id;

        $this->tahap = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Allocation::create([
            'school_id' => $sekolah->id, 'tahun' => 2026, 'bos_type_id' => $this->bosId,
            'stage_id' => null, 'nilai' => 50_000_000,
        ]);

        $this->actingAs($this->op);
    }

    /** POST pendapatan; $kembali = 'bku' meniru aksi cepat dari lembar BKU. */
    private function pencairan(string $tanggal, int $nilai, ?string $kembali = null)
    {
        $data = [
            'tanggal' => $tanggal, 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap->id,
            'keterangan' => 'Pencairan '.$tanggal,
            'rekening_id' => [$this->rekPendapatan], 'nilai' => [$nilai],
        ];
        if ($kembali !== null) {
            $data['kembali'] = $kembali;
        }

        return $this->withSession(['tahun' => 2026])->post(route('pendapatan.store'), $data);
    }

    /** POST belanja (metode transfer agar saldo kas tunai tidak jadi kendala). */
    private function belanja(string $tanggal, int $nilai, ?string $kembali = null)
    {
        $data = [
            'tanggal' => $tanggal, 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap->id,
            'metode_bayar' => 'transfer', 'keterangan' => 'Belanja '.$tanggal,
            'rekening_id' => [$this->rekBelanja], 'nilai' => [$nilai],
        ];
        if ($kembali !== null) {
            $data['kembali'] = $kembali;
        }

        return $this->withSession(['tahun' => 2026])->post(route('belanja.store'), $data);
    }

    public function test_pendapatan_dari_aksi_cepat_kembali_ke_bku_dengan_baris_dikilatkan(): void
    {
        // Bulan lain tetap ada, supaya jelas hanya baris yang ditandai yang berkilat.
        $this->pencairan('2026-01-05', 10_000_000, 'bku');

        $res = $this->pencairan('2026-02-10', 5_000_000, 'bku');
        $t = KasTransaction::orderByDesc('id')->first();
        $this->assertNotNull($t);

        $res->assertRedirect(route('bku', ['bulan' => '2026-02', 'baru' => $t->id]))
            ->assertSessionHas('ok');

        $html = $this->withSession(['tahun' => 2026])
            ->get(route('bku', ['bulan' => '2026-02', 'baru' => $t->id], false))
            ->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="kilat"'),
            'Hanya satu baris yang boleh dikilatkan.');
        $this->assertMatchesRegularExpression(
            '/<tr[^>]*class="[^"]*kilat[^"]*"[^>]*>.*?Pencairan 2026-02-10/s', $html,
            'Baris yang dikilatkan harus baris transaksi yang baru disimpan.');

        // Tanpa penanda, tidak ada baris yang berkilat.
        $tanpa = $this->withSession(['tahun' => 2026])
            ->get('/pembukuan/bku?bulan=2026-02')->assertOk()->getContent();
        $this->assertStringNotContainsString('class="kilat"', $tanpa);
    }

    public function test_belanja_dari_aksi_cepat_kembali_ke_bku_dengan_baris_dikilatkan(): void
    {
        $this->pencairan('2026-01-05', 10_000_000);

        $res = $this->belanja('2026-03-15', 2_000_000, 'bku');
        $t = KasTransaction::orderByDesc('id')->first();
        $this->assertNotNull($t);

        $res->assertRedirect(route('bku', ['bulan' => '2026-03', 'baru' => $t->id]))
            ->assertSessionHas('ok');

        $html = $this->withSession(['tahun' => 2026])
            ->get(route('bku', ['bulan' => '2026-03', 'baru' => $t->id], false))
            ->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="kilat"'));
        $this->assertMatchesRegularExpression('/<tr[^>]*class="[^"]*kilat[^"]*"[^>]*>.*?Belanja 2026-03-15/s', $html);
    }

    /**
     * Tanpa `?kembali=bku` perilaku lama tidak berubah: form pendapatan kembali
     * ke halaman asal, form belanja kembali ke daftar belanja.
     */
    public function test_tanpa_penanda_kembali_perilaku_lama_tetap(): void
    {
        $this->withSession(['tahun' => 2026])->from(route('pendapatan.index'))->post(route('pendapatan.store'), [
            'tanggal' => '2026-01-05', 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap->id,
            'keterangan' => 'Pencairan biasa',
            'rekening_id' => [$this->rekPendapatan], 'nilai' => [10_000_000],
        ])->assertRedirect(route('pendapatan.index'))->assertSessionHas('ok');

        $this->belanja('2026-03-15', 500_000)->assertRedirect(route('belanja.index'))->assertSessionHas('ok');
    }

    /** Id yang tidak ada di halaman tidak boleh mengilatkan baris apa pun. */
    public function test_penanda_baru_yang_tidak_ada_tidak_mengilatkan_baris(): void
    {
        $this->pencairan('2026-02-10', 5_000_000);

        $html = $this->withSession(['tahun' => 2026])
            ->get('/pembukuan/bku?bulan=2026-02&baru=999999')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="kilat"', $html);
    }
}
