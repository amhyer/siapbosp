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

    /** ARKAS mencetak 8 kolom dengan baris nomor 1–8 di atas judul kolom. */
    public function test_kolom_bernomor_dan_berjudul_seperti_arkas(): void
    {
        $html = $this->cetak();

        $this->assertStringContainsString(
            '<tr class="bku-nomor"><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>',
            $html
        );

        foreach (['Tanggal', 'Kode Kegiatan', 'Kode Rekening', 'No. Bukti', 'Uraian', 'Penerimaan', 'Pengeluaran', 'Saldo'] as $judul) {
            // Kolom angka memakai class="num", jadi dicocokkan lewat penutup tag.
            $this->assertStringContainsString('>'.$judul.'</th>', $html,
                'Judul kolom ARKAS hilang: '.$judul);
        }

        // Judul lama buatan sendiri tidak boleh tersisa.
        $this->assertStringNotContainsString('<th>Kegiatan</th>', $html);
        $this->assertStringNotContainsString('Penerimaan (Rp)', $html);
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
}
