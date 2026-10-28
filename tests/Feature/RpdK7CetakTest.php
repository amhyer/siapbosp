<?php

namespace Tests\Feature;

use App\Models\ArkasKode;
use App\Models\KasDetail;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RPD K7 — bentuknya diambil dari rpd-k7-output.pdf ARKAS 4.3.1:
 *   "REKAPITULASI REALISASI PENGGUNAAN DANA BOSP", "PERIODE TANGGAL : …",
 *   matriks 8 standar (baris) × 12 sub program (kolom) + kolom Jumlah,
 *   baris JUMLAH, empat baris saldo, lalu tanda tangan 2 kolom.
 *
 * Sel matriks = realisasi belanja dengan kode kegiatan `SS.SP.KK` (SS = standar,
 * SP = sub program) — bentuk kode yang diimpor dari ARKAS.
 */
class RpdK7CetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40305555', 'nama' => 'SDN Uji RPD', 'jenjang' => 'SD',
            'desa' => 'Jl. RPD No. 4', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan', 'status_sekolah' => 'negeri', 'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000]);
        $this->actingAs(User::create(['name' => 'Bendahara RPD', 'email' => 'rpd@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $this->school->id]));

        Penandatangan::create(['nip' => '197006202004111001', 'nama' => 'Nawawi Hamzah', 'jabatan' => 'Kepala Sekolah']);
        Penandatangan::create(['nip' => '198705172020121003', 'nama' => 'Hairuddin', 'jabatan' => 'Bendahara']);

        KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'pendapatan',
            'no_bku' => 1, 'tanggal' => '2026-01-05', 'metode_bayar' => 'transfer',
            'keterangan' => 'Terima Dana BOSP Tahap 1', 'total' => 12_000_000]);

        $belanjaA = KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'belanja',
            'no_bku' => 2, 'tanggal' => '2026-03-10', 'metode_bayar' => 'tunai',
            'keterangan' => 'Belanja ATK', 'total' => 5_000_000]);
        KasDetail::create(['kas_transaction_id' => $belanjaA->id, 'kode_kegiatan' => '02.03.01.',
            'uraian' => 'ATK Pembelajaran', 'nilai' => 5_000_000]);

        $belanjaB = KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'belanja',
            'no_bku' => 3, 'tanggal' => '2026-04-11', 'metode_bayar' => 'tunai',
            'keterangan' => 'Honor', 'total' => 7_000_000]);
        KasDetail::create(['kas_transaction_id' => $belanjaB->id, 'kode_kegiatan' => '05.12.01.',
            'uraian' => 'Honor Penjaga', 'nilai' => 7_000_000]);
    }

    private function cetak()
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rpd-k7'))
            ->assertOk();
    }

    /** Judul, periode, dan identitas seperti lembar RPD ARKAS. */
    public function test_rpd_berkop_dan_berperiode(): void
    {
        $this->cetak()
            ->assertSee('REKAPITULASI REALISASI PENGGUNAAN DANA BOSP')
            ->assertSee('PERIODE TANGGAL : 01 Januari 2026 s/d 31 Desember 2026')
            ->assertSee('Nama Sekolah')
            ->assertSee('SDN Uji RPD')
            ->assertSee('Kec. Makasar')
            ->assertSee('Kota Makassar')
            ->assertSee('Sulawesi Selatan')
            ->assertSee('40305555');
    }

    /** Matriks 8 standar x 12 sub program dengan kepala kolom ARKAS. */
    public function test_rpd_memuat_matriks_standar_dan_sub_program(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('8 STANDAR', $html);
        $this->assertStringContainsString('SUB PROGRAM', $html);
        $this->assertStringContainsString('No. Urut', $html);
        // Dua belas kolom sub program (nomor 1..12).
        foreach (range(1, 12) as $no) {
            $this->assertStringContainsString('>'.$no.'</th>', $html, 'Kolom sub program '.$no.' hilang');
        }
        $this->assertStringContainsString('Pembiayaan Penerimaan Peserta Didik Baru', $html);
        $this->assertStringContainsString('Pembiayaan untuk Pembayaran Honor', $html);
    }

    /** Baris standar memakai daftar baku bila master kode kegiatan belum ada. */
    public function test_rpd_memakai_daftar_standar_baku_bila_master_kosong(): void
    {
        $this->cetak()
            ->assertSee('Pengembangan Standar Isi')
            ->assertSee('Pengembangan standar pembiayaan')
            ->assertSee('Pengembangan dan implementasi sistem penilaian');
    }

    /** Master kode kegiatan level 1 menimpa daftar baku. */
    public function test_rpd_memakai_nama_standar_dari_master(): void
    {
        ArkasKode::create(['school_id' => $this->school->id, 'tahun' => 2026, 'kode' => '02.',
            'uraian' => 'Standar Proses (master)', 'level' => 1]);

        $this->cetak()
            ->assertSee('Standar Proses (master)')
            ->assertDontSee('Pengembangan Standar Isi');
    }

    /** Realisasi masuk ke sel menurut prefix SS.SP kode kegiatan. */
    public function test_rpd_menjumlah_realisasi_per_sel(): void
    {
        $html = $this->cetak()->getContent();

        // 02.03.01. -> standar 02, sub program 03 (5 juta); 05.12.01. -> 05/12 (7 juta).
        $this->assertStringContainsString('<td class="num" style="font-weight:600">5.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num" style="font-weight:600">7.000.000</td>', $html);
        // Baris JUMLAH dan total keseluruhan.
        $this->assertStringContainsString('JUMLAH', $html);
        $this->assertStringContainsString('<td class="num">12.000.000</td>', $html);
    }

    /** Empat baris saldo seperti lembar ARKAS. */
    public function test_rpd_mencetak_empat_baris_saldo(): void
    {
        $this->cetak()
            ->assertSee('Saldo periode sebelumnya')
            ->assertSee('Total penerimaan dana BOSP periode ini')
            ->assertSee('Total penggunaan dana BOSP periode ini')
            ->assertSee('Akhir saldo BOSP periode ini')
            ->assertSee(': Rp. 12.000.000')
            ->assertSee(': Rp. 0');
    }

    /** Tanda tangan 2 kolom: Kepala Sekolah & Bendahara/Penanggungjawab. */
    public function test_rpd_menandatangani_dua_kolom(): void
    {
        $this->cetak()
            ->assertSee('Menyetujui,')
            ->assertSee('Kepala Sekolah')
            ->assertSee('Nawawi Hamzah')
            ->assertSee('NIP. 197006202004111001')
            ->assertSee('Bendahara / Penanggungjawab Kegiatan')
            ->assertSee('Hairuddin')
            ->assertSee('NIP. 198705172020121003');
    }

    /** Tanpa transaksi lembar tetap terbuka (nol), bukan 500. */
    public function test_rpd_tanpa_transaksi_tetap_terbuka(): void
    {
        KasDetail::query()->delete();
        KasTransaction::query()->delete();

        $this->cetak()
            ->assertSee('REKAPITULASI REALISASI PENGGUNAAN DANA BOSP')
            ->assertSee('Saldo periode sebelumnya');
    }
}
