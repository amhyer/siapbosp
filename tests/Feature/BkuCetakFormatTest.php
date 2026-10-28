<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-6 — pemilih Format + Periode pada cetak BKU (ARKAS 4 Bab XXI).
 *
 * Format hanya mengubah rentang tanggal; kolom Saldo dan blok penutup harus
 * tetap konsisten dengan posisi kas sesungguhnya. Format Bulanan wajib identik
 * dengan perilaku lama (termasuk aturan watermark DRAFT per bulan).
 */
class BkuCetakFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private int $sid;

    private int $bosId;

    private int $rekPendapatan;

    private int $rekBelanja;

    private Stage $tahap1;

    private Stage $tahap2;

    protected function setUp(): void
    {
        parent::setUp();

        $s = School::create(['npsn' => '40309902', 'nama' => 'SDN Cetak Format', 'jenjang' => 'SD',
            'kecamatan' => 'Rappocini', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan']);
        $this->sid = $s->id;

        $this->op = User::create(['name' => 'Bendahara Cetak', 'email' => 'cetak@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $s->id]);

        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;
        $this->rekPendapatan = Rekening::firstOrCreate(['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Pemerintah', 'tipe' => 'pendapatan'])->id;
        $this->rekBelanja = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'],
            ['nama' => 'Honor Pengelola', 'tipe' => 'belanja'])->id;

        $this->tahap1 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $this->tahap2 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 2', 'urutan' => 2, 'is_open' => true]);

        Allocation::create(['school_id' => $s->id, 'tahun' => 2026, 'bos_type_id' => $this->bosId,
            'stage_id' => null, 'nilai' => 50_000_000]);

        $this->actingAs($this->op);

        // Kas 2026: Jan +10jt (tahap 1), Feb -1jt (tahap 1) +5jt (tahap 2), Mar -2jt (tahap 2).
        // Semua lewat Kas Bank karena pendapatan.store selalu mencatat ke bank.
        $this->pencairan('2026-01-10', 10_000_000, $this->tahap1);
        $this->belanja('2026-02-05', 1_000_000, $this->tahap1, 'transfer');
        $this->pencairan('2026-02-20', 5_000_000, $this->tahap2);
        $this->belanja('2026-03-15', 2_000_000, $this->tahap2, 'transfer');
    }

    private function pencairan(string $tanggal, int $nilai, Stage $tahap)
    {
        return $this->withSession(['tahun' => 2026])->post(route('pendapatan.store'), [
            'tanggal' => $tanggal, 'bos_type_id' => $this->bosId, 'stage_id' => $tahap->id,
            'keterangan' => 'Pencairan '.$tahap->nama.' '.$tanggal,
            'rekening_id' => [$this->rekPendapatan], 'nilai' => [$nilai],
        ])->assertSessionHas('ok');
    }

    private function belanja(string $tanggal, int $nilai, Stage $tahap, string $metode)
    {
        return $this->withSession(['tahun' => 2026])->post(route('belanja.store'), [
            'tanggal' => $tanggal, 'bos_type_id' => $this->bosId, 'stage_id' => $tahap->id,
            'metode_bayar' => $metode, 'keterangan' => 'Belanja '.$tanggal,
            'rekening_id' => [$this->rekBelanja], 'nilai' => [$nilai],
        ])->assertSessionHas('ok');
    }

    private function cetak(array $query = [])
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('bku.cetak', $query))
            ->assertOk();
    }

    /** Tutup 12 bulan sekaligus (tanpa lewat controller) untuk uji watermark. */
    private function tutupSemuaBulan(): void
    {
        foreach (range(1, 12) as $n) {
            // firstOrCreate: bulan yang sudah ditutup test lain tidak boleh ditulis ulang
            // (tabel punya UNIQUE school_id + bulan).
            BkuTutupBulan::firstOrCreate(
                ['school_id' => $this->sid, 'bulan' => sprintf('2026-%02d', $n)],
                ['tahun' => 2026, 'ditutup_oleh' => 'operator']
            );
        }
    }

    public function test_tanpa_parameter_tetap_format_bulanan_seperti_sebelumnya(): void
    {
        $r = $this->cetak(['bulan' => '2026-02']);

        // Format Bulanan memakai penulisan ARKAS: "BULAN : … TAHUN : …".
        $r->assertSee('BULAN : FEBRUARI TAHUN : 2026')
            ->assertSee('Saldo Bank Bulan Januari 2026')
            ->assertSee('Saldo Tunai Bulan Januari 2026')
            ->assertSee('05-02-2026')
            ->assertSee('20-02-2026')
            ->assertDontSee('15-03-2026')
            ->assertDontSee('10-01-2026');

        // Saldo akhir Februari: 10jt (Jan) - 1jt + 5jt
        $r->assertSee('Saldo Buku Kas Umum : <b>Rp. 14.000.000</b>', false);
    }

    public function test_halaman_cetak_menyediakan_pemilih_format_dan_periode(): void
    {
        $r = $this->cetak(['bulan' => '2026-02']);

        $r->assertSee('name="format"', false)
            ->assertSee('id="pilih-periode"', false)
            ->assertSee('Format')
            ->assertSee('Periode');

        foreach (['Bulanan', 'Tahunan', 'Tahapan', 'Akumulasi'] as $label) {
            $r->assertSee('>'.$label.'</option>', false);
        }

        // Pemilih tahap hanya muncul saat format tahapan dipilih.
        $r->assertDontSee('name="tahap"', false);
        $this->cetak(['bulan' => '2026-02', 'format' => 'tahapan'])
            ->assertSee('name="tahap"', false);
    }

    public function test_format_tahunan_memuat_seluruh_bulan_satu_tahun(): void
    {
        $r = $this->cetak(['bulan' => '2026-02', 'format' => 'tahunan']);

        $r->assertSee('PERIODE : TAHUN ANGGARAN 2026')
            ->assertSee('Saldo Awal Tahun')
            ->assertSee('10-01-2026')
            ->assertSee('05-02-2026')
            ->assertSee('20-02-2026')
            ->assertSee('15-03-2026')
            ->assertSee('Format : <b>Tahunan</b>', false);

        // 10jt - 1jt + 5jt - 2jt; blok penutup harus menjumlah ke angka yang sama
        $r->assertSee('Saldo Buku Kas Umum : <b>Rp. 12.000.000</b>', false);
        $r->assertSee('Jumlah : <b>Rp. 12.000.000</b>', false);
        // Tanda tangan ikut akhir rentang, bukan bulan acuan.
        $r->assertSee('31 Desember 2026');
    }

    public function test_format_akumulasi_berhenti_di_akhir_bulan_terpilih(): void
    {
        $r = $this->cetak(['bulan' => '2026-02', 'format' => 'akumulasi']);

        $r->assertSee('PERIODE : 1 JANUARI – 28 FEBRUARI 2026')
            ->assertSee('10-01-2026')
            ->assertSee('05-02-2026')
            ->assertSee('20-02-2026')
            ->assertDontSee('15-03-2026');

        $r->assertSee('Saldo Buku Kas Umum : <b>Rp. 14.000.000</b>', false);
    }

    public function test_format_tahapan_hanya_mencetak_baris_tahap_terpilih(): void
    {
        $r = $this->cetak(['bulan' => '2026-03', 'format' => 'tahapan', 'tahap' => $this->tahap1->id]);

        $r->assertSee('PERIODE : TAHAPAN TAHAP 1 TAHUN 2026')
            ->assertSee('10-01-2026')
            ->assertSee('05-02-2026')
            ->assertDontSee('20-02-2026')
            ->assertDontSee('15-03-2026')
            ->assertSee('Kolom Saldo tetap posisi kas sesungguhnya');

        // Baris terakhir tahap 1 di 05-02: 10jt - 1jt. Pencairan tahap 2 (20 Feb)
        // terjadi sesudahnya, jadi tidak boleh masuk penutup.
        $r->assertSee('Saldo Buku Kas Umum : <b>Rp. 9.000.000</b>', false);
        // Penutup memakai snapshot baris terakhir tahap 1, bukan posisi 31 Desember:
        // tanpa snapshot, blok ini akan menjumlah ke 14jt sementara saldo 9jt.
        $r->assertSee('Jumlah : <b>Rp. 9.000.000</b>', false);

        // Tahap 2 memuat barisnya sendiri.
        $this->cetak(['bulan' => '2026-03', 'format' => 'tahapan', 'tahap' => $this->tahap2->id])
            ->assertSee('PERIODE : TAHAPAN TAHAP 2 TAHUN 2026')
            ->assertSee('20-02-2026')
            ->assertSee('15-03-2026')
            ->assertDontSee('05-02-2026');
    }

    public function test_format_tak_dikenal_jatuh_ke_bulanan(): void
    {
        $this->cetak(['bulan' => '2026-02', 'format' => 'ngawur'])
            // Format tak dikenal jatuh ke Bulanan → penulisan ARKAS "BULAN : … TAHUN : …".
            ->assertSee('BULAN : FEBRUARI TAHUN : 2026')
            ->assertDontSee('15-03-2026');
    }

    public function test_watermark_draft_mengikuti_seluruh_bulan_dalam_rentang(): void
    {
        // Februari ditutup saja → format bulanan Februari bersih…
        BkuTutupBulan::create(['school_id' => $this->sid, 'tahun' => 2026, 'bulan' => '2026-02', 'ditutup_oleh' => 'operator']);
        $this->cetak(['bulan' => '2026-02'])
            ->assertDontSee('Watermark DRAFT aktif');

        // …tetapi Akumulasi s/d Februari masih memuat Januari yang terbuka.
        $this->cetak(['bulan' => '2026-02', 'format' => 'akumulasi'])
            ->assertSee('Watermark DRAFT aktif')
            ->assertSee('bulan dalam periode ini belum ditutup');

        // Semua bulan ditutup → watermark hilang untuk semua format.
        $this->tutupSemuaBulan();
        foreach (['bulanan', 'tahunan', 'akumulasi', 'tahapan'] as $format) {
            $this->cetak(['bulan' => '2026-02', 'format' => $format])
                ->assertDontSee('Watermark DRAFT aktif');
        }
    }

    public function test_pemilih_format_tidak_ikut_tercetak(): void
    {
        // Pemilih berada di toolbar ber-kelas nonprint, jadi hilang saat dicetak.
        $html = $this->cetak(['bulan' => '2026-02', 'format' => 'tahunan'])->getContent();
        $this->assertStringContainsString('toolbar nonprint', $html);
        $this->assertMatchesRegularExpression('/<form[^>]*action="[^"]*bku\/cetak"[^>]*>[\s\S]*?name="format"/', $html);
    }
}
