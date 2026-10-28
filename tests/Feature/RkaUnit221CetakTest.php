<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RKA/DPA Unit Kerja 2.2.1 — bentuknya dari rka-unit-221-output.pdf &
 * dpa-unit-221-output.pdf: pengelompokan menurut KODE REKENING (`5.` → `5.1.`/`5.2.`
 * → 4 segmen → kode rekening penuh → rincian) dengan kolom
 * Kode Rekening / Uraian / Volume / Satuan / Harga Satuan / Jumlah (6 = 3 x 5),
 * blok Program…Kegiatan dan tabel Indikator & Tolok Ukur Kinerja Belanja Langsung.
 */
class RkaUnit221CetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private RkasHeader $header;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40307777', 'nama' => 'SDN Uji Dua Dua Satu', 'jenjang' => 'SD',
            'desa' => 'Jl. 221 No. 1', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan', 'status_sekolah' => 'negeri', 'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000]);

        $this->actingAs(User::create(['name' => 'Bendahara 221', 'email' => 'rka221@sdn.test',
            'password' => 'secret123', 'role' => 'operator', 'school_id' => $this->school->id]));

        Penandatangan::create(['nip' => '198112072007012013', 'nama' => 'Andi Windayani',
            'jabatan' => 'Kepala Sekolah']);

        $tahap1 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $bos = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Allocation::create(['school_id' => $this->school->id, 'tahun' => 2026, 'bos_type_id' => $bos->id,
            'stage_id' => $tahap1->id, 'nilai' => 100_000_000]);

        $this->header = RkasHeader::create(['school_id' => $this->school->id, 'tahun' => 2026,
            'revisi_ke' => 1, 'status' => 'draft', 'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni']);

        $bahan = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0012'],
            ['nama' => 'Belanja Bahan-Bahan Lainnya', 'tipe' => 'belanja']);
        $honor = Rekening::firstOrCreate(['kode' => '5.1.02.02.01.0013'],
            ['nama' => 'Belanja Jasa Tenaga Pendidikan / Honorarium Guru', 'tipe' => 'belanja']);
        $modal = Rekening::firstOrCreate(['kode' => '5.2.02.05.01.0005'],
            ['nama' => 'Belanja Modal Alat Kantor Lainnya', 'tipe' => 'belanja']);

        // Dua rincian pada rekening yang sama + satu rekening lain + satu belanja modal.
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $bahan->id,
            'kode_kegiatan' => '03.01.01.', 'uraian' => 'Bahan-bahan perlengkapan kotak p3k', 'banyak' => 2,
            'satuan' => 'pack', 'harga_satuan' => 500_000, 'jumlah' => 1_000_000]);
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $bahan->id,
            'kode_kegiatan' => '03.01.01.', 'uraian' => 'Belanja ATK', 'banyak' => 1,
            'satuan' => 'paket', 'harga_satuan' => 2_000_000, 'jumlah' => 2_000_000]);
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $honor->id,
            'kode_kegiatan' => '07.12.01.', 'uraian' => 'Honor Guru Kelas', 'banyak' => 1,
            'satuan' => 'orang / bulan', 'harga_satuan' => 4_000_000, 'jumlah' => 4_000_000]);
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $modal->id,
            'kode_kegiatan' => '05.08.01.', 'uraian' => 'Pembuatan Papan Bicara', 'banyak' => 2,
            'satuan' => 'buah', 'harga_satuan' => 5_000_000, 'jumlah' => 10_000_000]);
    }

    private function cetak(array $query = [])
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rka-unit', $query + ['varian' => 'rka221']))
            ->assertOk();
    }

    /** Judul & kepala lembar 2.2.1. */
    public function test_rka_221_berjudul_dan_berblok_identitas(): void
    {
        $this->cetak()
            ->assertSee('LEMBAR KERTAS KERJA')
            ->assertSee('UNIT KERJA')
            ->assertSee('PEMERINTAH KOTA MAKASSAR')
            ->assertSee('TAHUN ANGGARAN 2026')
            ->assertSee(': 40307777 - SDN UJI DUA DUA SATU')
            ->assertSee('Program')
            ->assertSee('Sub Program')
            ->assertSee('Lokasi Kegiatan')
            ->assertSee(': SDN Uji Dua Dua Satu')
            ->assertSee('Jumlah Tahun n-1')
            ->assertSee('Jumlah Tahun n')
            ->assertSee('Jumlah Tahun n+1')
            ->assertSee('Kegiatan');
    }

    /** Tabel indikator & tolok ukur kinerja. */
    public function test_rka_221_memuat_tabel_indikator(): void
    {
        $this->cetak()
            ->assertSee('Indikator &amp; Tolok Ukur Kinerja Belanja Langsung', false)
            ->assertSee('Tolok Ukur Kinerja')
            ->assertSee('Target Kinerja')
            ->assertSee('Capaian Program')
            ->assertSee('Masukan')
            ->assertSee('Keluaran')
            ->assertSee('Hasil')
            ->assertSee('Kelompok Sasaran Kegiatan')
            ->assertSee('Rp. 17.000.000');
    }

    /** Varian DPA memakai judul RKAS dan "Rincian Dokumen Pelaksanaan Anggaran". */
    public function test_dpa_221_memakai_judul_rkas(): void
    {
        $this->cetak(['varian' => 'dpa221'])
            ->assertSee('RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)')
            ->assertDontSee('LEMBAR KERTAS KERJA')
            ->assertSee('Rincian Dokumen Pelaksanaan Anggaran Belanja Langsung')
            ->assertDontSee('Rincian Anggaran Belanja Langsung<br>', false);
    }

    /** Kepala tabel 6 kolom dengan penomoran ARKAS. */
    public function test_rka_221_berkepala_kolom_rincian_perhitungan(): void
    {
        $this->cetak()
            ->assertSee('Kode Rekening')
            ->assertSee('Rincian Perhitungan')
            ->assertSee('Volume')
            ->assertSee('Satuan')
            ->assertSee('Harga Satuan')
            ->assertSee('Jumlah (Rp)')
            ->assertSee('6=(3 x 5)')
            ->assertSee('Menurut Program dan Per Kegiatan Unit Kerja');
    }

    /** Baris: 5. → 5.1./5.2. → prefix 4 segmen → rekening → rincian. */
    public function test_rka_221_membentuk_pohon_rekening(): void
    {
        $html = $this->cetak()->getContent();

        foreach (['5.', '5.1.', '5.1.02.01', '5.1.02.02', '5.1.02.01.01.0012',
            '5.1.02.02.01.0013', '5.2.', 'BELANJA LANGSUNG', '5.2.02.05',
            '5.2.02.05.01.0005'] as $kode) {
            $this->assertStringContainsString('>'.$kode.'</td>', $html, 'Baris hilang: '.$kode);
        }

        // Subtotal prefix 5.1.02.01 = 3.000.000, 5.1. = 7.000.000, total 17.000.000.
        $this->assertStringContainsString('>3.000.000</td>', $html);
        $this->assertStringContainsString('>7.000.000</td>', $html);
        $this->assertStringContainsString('>17.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num">10.000.000</td>', $html);
    }

    /** Baris rincian memuat volume, satuan, harga satuan, jumlah. */
    public function test_rka_221_mencetak_volume_satuan_harga(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('Bahan-bahan perlengkapan kotak p3k', $html);
        $this->assertStringContainsString('>2</td>', $html);
        $this->assertStringContainsString('>pack</td>', $html);
        $this->assertStringContainsString('>500.000</td>', $html);
        $this->assertStringContainsString('>1.000.000</td>', $html);
        $this->assertStringContainsString('orang / bulan', $html);
    }

    /** Bagian penutup: keterangan, TAPD, tanda tangan, kaki halaman. */
    public function test_rka_221_memuat_keterangan_tapd_dan_tanda_tangan(): void
    {
        $this->cetak()
            ->assertSee('Keterangan')
            ->assertSee('Tanggal Pembahasan')
            ->assertSee('Catatan Hasil Pembahasan')
            ->assertSee('TAPD PD')
            ->assertSee('TANDATANGAN')
            ->assertSee('Mengetahui,')
            ->assertSee('Kepala Sekolah')
            ->assertSee('Andi Windayani')
            ->assertSee('NIP : 198112072007012013')
            ->assertSee('Kec. Makasar,', false)
            ->assertSee('RKAS Unit Kerja 2.2.1 - NPSN : 40307777', false);
    }

    /** Tanpa rincian lembar tetap terbuka. */
    public function test_rka_221_tanpa_rincian_tetap_terbuka(): void
    {
        RkasDetail::query()->delete();
        $this->header->delete();

        $this->cetak()->assertSee('Belum ada rincian RKAS untuk tahun ini.');
    }

    /** Varian 2.2 dan ringkas tidak terpengaruh. */
    public function test_varian_lain_tetap_jalan(): void
    {
        $this->cetak(['varian' => 'rka22'])
            ->assertSee('Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan');

        $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rka-unit'))
            ->assertOk()
            ->assertSee('Rincian Anggran Pendapatan dan Belanja');
    }
}
