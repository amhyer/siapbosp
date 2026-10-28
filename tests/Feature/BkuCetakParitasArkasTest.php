<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paritas cetak BKU terhadap keluaran ARKAS Desktop 4.3.1.
 *
 * Acuan: berkas BKU asli sekolah (NPSN 40313912, Januari 2026) pada paket
 * aplikasi ARKAS 4.3.1, dirangkum di docs/ARKAS-4.3.1-RUJUKAN.md. Yang diuji
 * hanya bentuk dokumen (judul kolom, penomoran, label jumlah, blok penutup,
 * susunan tanda tangan) — bukan angka, karena angka sudah dijaga
 * BkuCetakFormatTest.
 */
class BkuCetakParitasArkasTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private int $bosId;

    private int $rekPendapatan;

    protected function setUp(): void
    {
        parent::setUp();

        $s = School::create(['npsn' => '40313912', 'nama' => 'SDN Paritas Arkas', 'jenjang' => 'SD',
            'desa' => 'Mongisidi', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan']);

        $this->op = User::create(['name' => 'Bendahara Paritas', 'email' => 'paritas@sdn.test',
            'password' => 'secret123', 'role' => 'operator', 'school_id' => $s->id]);

        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;
        $this->rekPendapatan = Rekening::firstOrCreate(['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Pemerintah', 'tipe' => 'pendapatan'])->id;

        $tahap = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Allocation::create(['school_id' => $s->id, 'tahun' => 2026, 'bos_type_id' => $this->bosId,
            'stage_id' => null, 'nilai' => 50_000_000]);

        $this->actingAs($this->op);

        $this->withSession(['tahun' => 2026])->post(route('pendapatan.store'), [
            'tanggal' => '2026-01-10', 'bos_type_id' => $this->bosId, 'stage_id' => $tahap->id,
            'keterangan' => 'Pencairan Tahap 1', 'rekening_id' => [$this->rekPendapatan],
            'nilai' => [10_000_000],
        ])->assertSessionHas('ok');
    }

    private function cetak(): string
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('bku.cetak', ['bulan' => '2026-01']))
            ->assertOk()
            ->getContent();
    }

    /** ARKAS mencetak judul kolom (kapital) DULU, lalu baris nomor 1–8. */
    public function test_kolom_bernomor_dan_berjudul_seperti_arkas(): void
    {
        $html = $this->cetak();

        foreach (['TANGGAL', 'KODE<br>KEGIATAN', 'KODE<br>REKENING', 'NO. BUKTI', 'URAIAN',
            'PENERIMAAN', 'PENGELUARAN', 'SALDO'] as $judul) {
            $this->assertStringContainsString('>'.$judul.'</th>', $html,
                'Judul kolom ARKAS hilang: '.$judul);
        }

        // Urutan ARKAS: baris judul kolom lebih dulu, baris nomor 1–8 sesudahnya.
        $posJudul = strpos($html, '>TANGGAL</th>');
        $posNomor = strpos($html, '<tr class="bku-nomor"><th>1</th><th>2</th>');
        $this->assertNotFalse($posJudul, 'Baris judul kolom tidak ditemukan.');
        $this->assertNotFalse($posNomor, 'Baris nomor kolom tidak ditemukan.');
        $this->assertLessThan($posNomor, $posJudul, 'Nomor kolom harus berada di bawah judul kolom.');

        // Judul lama buatan sendiri tidak boleh tersisa.
        $this->assertStringNotContainsString('<th>Kegiatan</th>', $html);
        $this->assertStringNotContainsString('Penerimaan (Rp)', $html);
    }

    /** Kepala dokumen mengikuti BKU ARKAS: judul berjarak + blok identitas. */
    public function test_kepala_dokumen_mengikuti_arkas(): void
    {
        $html = $this->cetak();

        $this->assertStringContainsString('B U K U   K A S   U M U M', $html);
        $this->assertStringContainsString('BULAN : JANUARI TAHUN : 2026', $html);

        foreach (['NPSN : 40313912', 'Nama Sekolah : ', 'Desa/Kecamatan : ', 'Kabupaten / Kota : ',
            'Provinsi : ', 'Sumber Dana : '] as $baris) {
            $this->assertStringContainsString($baris, $html, 'Baris identitas hilang: '.$baris);
        }

        // Penanda dokumen di kanan atas.
        $this->assertStringContainsString('>BKU</div>', $html);
    }

    /** ARKAS memisah saldo awal bank & tunai, dan mencetak 0 (bukan kosong). */
    public function test_saldo_awal_bank_dan_tunai_terpisah(): void
    {
        $html = $this->cetak(); // Januari 2026 → bulan sebelumnya Desember 2025

        $this->assertStringContainsString('Saldo Bank Bulan Desember 2025', $html);
        $this->assertStringContainsString('Saldo Tunai Bulan Desember 2025', $html);
        $this->assertStringContainsString('<td class="num">0</td>', $html,
            'Kolom uang yang kosong harus tercetak 0 seperti ARKAS.');
    }

    /** Baris rekap ARKAS berlabel "Jumlah" (bukan JUMLAH kapital). */
    public function test_baris_rekap_berlabel_jumlah(): void
    {
        $html = $this->cetak();

        $this->assertStringContainsString('>Jumlah</td>', $html);
        $this->assertStringNotContainsString('>JUMLAH</td>', $html);
    }

    /** Angka kolom tanpa awalan "Rp"; blok penutup memakai "Rp.". */
    public function test_angka_kolom_tanpa_rp_dan_penutup_memakai_rp_titik(): void
    {
        $html = $this->cetak();

        // Baris transaksi: 10.000.000 apa adanya, tanpa "Rp".
        $this->assertStringContainsString('<td class="num">10.000.000</td>', $html);
        $this->assertStringNotContainsString('<td class="num">Rp 10.000.000</td>', $html);

        $this->assertStringContainsString('Saldo Buku Kas Umum : <b>Rp. 10.000.000</b>', $html);
        $this->assertStringContainsString('<br>- Saldo Bank : <b>Rp. 10.000.000</b>', $html);
        $this->assertStringContainsString('<br>- Saldo Kas Tunai : <b>Rp. 0</b>', $html);
    }

    /** Tanda tangan ARKAS: 2 kolom, tempat memakai kecamatan, tanpa kolom komite. */
    public function test_tanda_tangan_dua_kolom_memakai_kecamatan(): void
    {
        $html = $this->cetak();

        $this->assertStringContainsString('grid-template-columns:1fr 1fr', $html);
        $this->assertStringContainsString('Menyetujui,<br>Kepala Sekolah', $html);
        $this->assertStringContainsString('Kec. Makasar, 31 Januari 2026<br>Bendahara', $html);
        $this->assertStringNotContainsString('Komite Sekolah', $html);
    }

    /** Bunga/pajak bank adalah transaksi sungguhan; jangan ada baris karangan. */
    public function test_tidak_ada_baris_pajak_bunga_karangan(): void
    {
        $html = $this->cetak();

        $this->assertStringNotContainsString('>Pajak Bunga</td>', $html,
            'Baris Pajak Bunga tidak boleh dicetak paksa; ARKAS mengambilnya dari transaksi.');
    }

    /** Kaki halaman ARKAS: identitas BKU di kiri, "Halaman X dari Y" di kanan. */
    public function test_kaki_halaman_memakai_margin_box_arkas(): void
    {
        $html = $this->cetak();

        $this->assertStringContainsString('@bottom-left', $html, 'Kaki kiri ARKAS hilang.');
        // Nomor halaman dipasang layouts.print untuk semua dokumen cetak.
        $this->assertStringContainsString('@bottom-right', $html);
        $this->assertStringContainsString('counter(page)', $html);
        $this->assertStringContainsString('counter(pages)', $html);

        // Teks kaki kiri: BKU <Bulan Akhir> <Tahun> - NPSN : …, Nama Sekolah : …
        $this->assertStringContainsString('content: "BKU Januari 2026 - NPSN : 40313912, Nama Sekolah : ', $html);
    }
}
