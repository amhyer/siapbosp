<?php

namespace Tests\Feature;

use App\Models\KasDetail;
use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dua dokumen cetak yang sebelumnya belum ada di SiapBOSP, dibuat mengikuti
 * keluaran ARKAS 4.3.1:
 *
 *  - BKU Pajak  ← bku-pajak-output.pdf ("B U K U   P E M B A N T U   P A J A K",
 *                 kolom PENERIMAAN/DEBIT per jenis pajak + PENGELUARAN/KREDIT + SALDO)
 *  - BHM        ← bhm-output.pdf ("REKAPITULASI REALISASI BELANJA DANA BOSP
 *                 ( BARANG MODAL / ASET )", kolom Jumlah Barang / Harga Satuan / Realisasi)
 */
class BkuPajakBhmCetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40307777', 'nama' => 'SDN Uji Pajak BHM', 'jenjang' => 'SD',
            'desa' => 'Jl. Uji No. 1', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan']);

        $this->op = User::create(['name' => 'Bendahara Uji', 'email' => 'uji-pajak@sdn.test',
            'password' => 'secret123', 'role' => 'operator', 'school_id' => $this->school->id]);

        Penandatangan::create(['school_id' => $this->school->id, 'nama' => 'Andi Windayani, S.Pd.',
            'nip' => '198112072007012013', 'jabatan' => 'Kepala Sekolah']);
        Penandatangan::create(['school_id' => $this->school->id, 'nama' => 'Lilis Agustina, S.Pd.',
            'nip' => '199708122024212017', 'jabatan' => 'Bendahara']);

        $this->actingAs($this->op);

        $rekModal = Rekening::firstOrCreate(['kode' => '5.2.02.01.01.0001'],
            ['nama' => 'Belanja Modal Peralatan dan Mesin', 'tipe' => 'belanja']);
        $ppn = RekeningPajak::firstOrCreate(['kode' => 'ppn'], ['nama' => 'PPN (Pajak Pertambahan Nilai)']);

        // Belanja modal Maret: 2 unit × 2.500.000 = 5.000.000, PPN dipungut 500.000.
        $belanja = KasTransaction::create(['tahun' => 2026, 'type' => 'belanja', 'no_bku' => 1,
            'no_bukti' => 'BBU07', 'tanggal' => '2026-03-10', 'metode_bayar' => 'transfer',
            'keterangan' => 'Belanja laptop', 'total' => 5_000_000]);
        KasDetail::create(['kas_transaction_id' => $belanja->id, 'rekening_id' => $rekModal->id,
            'kode_kegiatan' => 'K01', 'uraian' => 'Laptop', 'nilai' => 5_000_000,
            'volume' => 2, 'satuan' => 'unit']);
        KasTax::create(['kas_transaction_id' => $belanja->id, 'rekening_pajak_id' => $ppn->id,
            'kode_billing' => '8202603100001', 'nilai' => 500_000, 'status' => 'belum']);

        // Setoran PPN 20 Maret: melunasi pungutan di atas.
        $setor = KasTransaction::create(['tahun' => 2026, 'type' => 'setor_pajak', 'no_bku' => 2,
            'no_bukti' => 'SSP01', 'tanggal' => '2026-03-20', 'metode_bayar' => 'transfer',
            'keterangan' => 'Setor PPN Maret', 'total' => 500_000]);
        KasTax::create(['kas_transaction_id' => $setor->id, 'rekening_pajak_id' => $ppn->id,
            'kode_billing' => '8202603100001', 'ntpn' => 'ABC123', 'nilai' => 500_000, 'status' => 'sudah']);
    }

    private function cetakPajak(string $bulan = '2026-03')
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('bku.pajak', ['bulan' => $bulan]))
            ->assertOk();
    }

    /** Kop, grup kolom, dan kolom jenis pajak mengikuti bku-pajak-output.pdf. */
    public function test_bku_pajak_berkop_dan_berkolom_seperti_arkas(): void
    {
        $r = $this->cetakPajak();

        $r->assertSee('B U K U   P E M B A N T U   P A J A K')
            ->assertSee('BULAN : MARET TAHUN : 2026')
            ->assertSee('BKU - PAJAK')
            ->assertSee('NPSN : 40307777')
            ->assertSee('PENERIMAAN / DEBIT')
            ->assertSee('PENGELUARAN/<br>KREDIT', false)
            ->assertSee('>PPN</th>', false)
            ->assertSee('NO. KODE');

        // Tanda tangan dua kolom dengan tempat = kecamatan.
        $r->assertSee('grid-template-columns:1fr 1fr', false)
            ->assertSee('Menyetujui,<br>Kepala Sekolah', false)
            ->assertSee('Kec. Makasar, 31 Maret 2026<br>Bendahara', false);
    }

    /** Pungutan masuk kolom jenisnya; setoran masuk kolom kredit; saldo = utang pajak. */
    public function test_bku_pajak_memisah_pungutan_dan_setoran(): void
    {
        $html = $this->cetakPajak()->getContent();

        $this->assertStringContainsString('Belanja laptop', $html, 'Baris pungutan hilang.');
        $this->assertStringContainsString('Setor PPN Maret', $html, 'Baris setoran hilang.');
        $this->assertStringContainsString('8202603100001', $html, 'Nomor kode/billing pungutan hilang.');
        $this->assertStringContainsString('ABC123', $html, 'NTPN setoran hilang.');

        // 500.000 dipungut lalu disetor → utang pajak kembali 0.
        $this->assertStringContainsString(
            'Utang pajak yang belum disetor s/d 31 Maret 2026 : <b>Rp. 0</b>', $html);
    }

    /** Kolom mengikuti jenis pajak yang ada di periode, bukan daftar tetap. */
    public function test_bku_pajak_hanya_mencetak_jenis_pajak_yang_ada(): void
    {
        $html = $this->cetakPajak()->getContent();

        $this->assertStringContainsString('>PPN</th>', $html);
        $this->assertStringNotContainsString('>PPh 21</th>', $html,
            'Kolom pajak tanpa data tidak boleh dicetak.');
    }

    public function test_bku_pajak_kosong_tetap_terbuka(): void
    {
        $this->cetakPajak('2026-06')
            ->assertSee('Tidak ada pungutan atau penyetoran pajak pada periode ini.')
            ->assertSee('BULAN : JUNI TAHUN : 2026');
    }

    /** BHM: judul, kop, kolom Jumlah Barang / Harga Satuan / Realisasi. */
    public function test_bhm_berkop_dan_berkolom_seperti_arkas(): void
    {
        $r = $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.bhm', ['bulan' => 3]))
            ->assertOk();

        $r->assertSee('REKAPITULASI REALISASI BELANJA DANA BOSP ( BARANG MODAL / ASET )')
            ->assertSee('BULAN : MARET TAHUN : 2026')
            ->assertSee('BHM')
            ->assertSee('NPSN : 40307777')
            ->assertSee('JUMLAH<br>BARANG', false)
            ->assertSee('HARGA<br>SATUAN', false)
            ->assertSee('REALISASI')
            ->assertSee('KODE<br>REKENING', false);
    }

    /** Hanya belanja kode 5.2 (modal/aset) yang masuk BHM, lengkap dengan volume & harga. */
    public function test_bhm_memuat_barang_modal_dengan_volume_dan_harga(): void
    {
        $html = $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.bhm', ['bulan' => 3]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Laptop', $html);
        $this->assertStringContainsString('unit', $html);
        $this->assertStringContainsString('5.2.02.01.01.0001', $html);
        $this->assertStringContainsString('BBU07', $html);
        // 2 unit × 2.500.000
        $this->assertStringContainsString('<td class="num">2</td>', $html);
        $this->assertStringContainsString('<td class="num">2.500.000</td>', $html);
        $this->assertStringContainsString('<td class="num">5.000.000</td>', $html);
        $this->assertStringContainsString(
            'Realisasi belanja barang modal / aset s/d 31 Maret 2026 : <b>Rp. 5.000.000</b>', $html);
    }

    /** Bulan tanpa belanja modal tidak boleh menampilkan baris palsu. */
    public function test_bhm_kosong_tetap_terbuka(): void
    {
        $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.bhm', ['bulan' => 7]))
            ->assertOk()
            ->assertSee('Tidak ada belanja barang modal (kode rekening 5.2) pada bulan ini.')
            ->assertSee('BULAN : JULI TAHUN : 2026');
    }

    /** Kedua dokumen bisa dijangkau dari halaman yang sudah ada. */
    public function test_tautan_dokumen_tersedia_dari_halaman_induk(): void
    {
        $this->withSession(['tahun' => 2026])
            ->get(route('bku', ['bulan' => '2026-03']))
            ->assertOk()
            ->assertSee(route('bku.pajak'), false);

        $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.modal', ['bulan' => 3]))
            ->assertOk()
            ->assertSee(route('laporan-arkas.bhm'), false);
    }
}
