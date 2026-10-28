<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\ArkasKode;
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
 * RKA/DPA Unit Kerja 2.2 — bentuknya dari rka-unit-22-output.pdf & dpa-unit-22-output.pdf:
 *   "Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan" dengan kolom
 *   Kode Rekening / Kode Program / Kode Sub Prog / Kode Kegiatan / Uraian /
 *   Lokasi Kegiatan / Target Kinerja (Kuantitatif) / Barang & Jasa / Modal /
 *   Jumlah (9 = 7 + 8), tiga tingkat baris (program → sub program → rincian),
 *   dan kaki halaman "RKAS Unit Kerja 2.2 - NPSN : …".
 */
class RkaUnit22CetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private RkasHeader $header;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40309999', 'nama' => 'SDN Uji Dua Dua', 'jenjang' => 'SD',
            'desa' => 'Jl. 22 No. 22', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan', 'status_sekolah' => 'negeri', 'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000]);

        $this->actingAs(User::create(['name' => 'Bendahara 22', 'email' => 'rka22@sdn.test',
            'password' => 'secret123', 'role' => 'operator', 'school_id' => $this->school->id]));

        Penandatangan::create(['school_id' => $this->school->id, 'nip' => '197006202004111001',
            'nama' => 'Nawawi Hamzah', 'jabatan' => 'Kepala Sekolah']);

        // Tahap + alokasi: dipakai jalur ringkas (varian rka/dpa) yang tetap hidup.
        $tahap1 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $bos = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Allocation::create(['school_id' => $this->school->id, 'tahun' => 2026, 'bos_type_id' => $bos->id,
            'stage_id' => $tahap1->id, 'nilai' => 100_000_000]);

        // Master kode kegiatan: program / sub program / kegiatan.
        foreach ([['03.', 'Standar Proses', 1], ['03.01.', 'Penerimaan Murid baru', 2],
            ['03.01.01.', 'Pelaksanaan Pendaftaran Murid Baru (PMB)', 3]] as [$kode, $uraian, $level]) {
            ArkasKode::create(['school_id' => $this->school->id, 'tahun' => 2026,
                'kode' => $kode, 'uraian' => $uraian, 'level' => $level]);
        }

        $this->header = RkasHeader::create(['school_id' => $this->school->id, 'tahun' => 2026,
            'revisi_ke' => 1, 'status' => 'draft', 'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni']);

        $jasa = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0052'],
            ['nama' => 'Belanja Makanan dan Minuman Rapat', 'tipe' => 'belanja']);
        $modal = Rekening::firstOrCreate(['kode' => '5.2.02.05.01.0005'],
            ['nama' => 'Komputer', 'tipe' => 'belanja']);

        // Rincian dengan lokasi & target kustom.
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $jasa->id,
            'kode_kegiatan' => '03.01.01.', 'uraian' => 'Konsumsi rapat panitia', 'banyak' => 2,
            'satuan' => 'paket', 'harga_satuan' => 500_000, 'jumlah' => 1_000_000,
            'lokasi_kegiatan' => 'Aula Sekolah', 'target_kinerja' => '2 paket rapat']);

        // Rincian tanpa lokasi/target → dipakai nilai turunan.
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $modal->id,
            'kode_kegiatan' => '03.01.01.', 'uraian' => 'Komputer ruang guru', 'banyak' => 1,
            'satuan' => 'unit', 'harga_satuan' => 5_000_000, 'jumlah' => 5_000_000]);
    }

    private function cetak(array $query = [])
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rka-unit', $query + ['varian' => 'rka22']))
            ->assertOk();
    }

    /** Judul & bagian 2.2 seperti rka-unit-22-output.pdf. */
    public function test_rka_22_berjudul_dan_berbagian_belanja_langsung(): void
    {
        $this->cetak()
            ->assertSee('LEMBAR KERTAS KERJA')
            ->assertSee('UNIT KERJA')
            ->assertSee('PEMERINTAH KOTA MAKASSAR')
            ->assertSee('TAHUN ANGGARAN 2026')
            ->assertSee(': 1.01 - PENDIDIKAN')
            ->assertSee(': 40309999 - SDN UJI DUA DUA')
            ->assertSee('Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan')
            ->assertSee('Lokasi Kegiatan')
            ->assertSee('Target Kinerja')
            ->assertSee('Barang &amp;<br>Jasa', false)
            ->assertSee('Jumlah<br>(9 = 7 + 8)', false);
    }

    /** Varian DPA memakai judul RKAS, isinya sama. */
    public function test_dpa_22_memakai_judul_rkas(): void
    {
        $this->cetak(['varian' => 'dpa22'])
            ->assertSee('LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)')
            ->assertDontSee('LEMBAR KERTAS KERJA')
            // dpa-unit-22-output.pdf memakai "Rekapitulasi …", rka-unit-22 "Rincian …".
            ->assertSee('Rekapitulasi Belanja Langsung Berdasarkan Program dan Kegiatan')
            ->assertDontSee('Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan');
    }

    /** Tiga tingkat baris: program, sub program, lalu rincian. */
    public function test_rka_22_membentuk_tiga_tingkat_baris(): void
    {
        $html = $this->cetak()->getContent();

        foreach (['03.', '03.01.'] as $kode) {
            $this->assertStringContainsString($kode.'</td>', $html, 'Baris ringkasan hilang: '.$kode);
        }
        $this->assertStringContainsString('Standar Proses', $html);
        $this->assertStringContainsString('Penerimaan Murid baru', $html);
        $this->assertStringContainsString('Pelaksanaan Pendaftaran Murid Baru (PMB)', $html);
    }

    /** Kolom lokasi & target memakai isian rincian bila ada, turunan bila kosong. */
    public function test_rka_22_memakai_lokasi_dan_target_rincian(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('Aula Sekolah', $html);
        $this->assertStringContainsString('2 paket rapat', $html);
        // Rincian kedua kosong → nama sekolah dan volume + satuan.
        $this->assertStringContainsString('<td>SDN Uji Dua Dua</td>', $html);
        $this->assertStringContainsString('<td>1 unit</td>', $html);
    }

    /** Barang & Jasa vs Modal dipisah dari prefix kode rekening. */
    public function test_rka_22_memisahkan_barang_jasa_dan_modal(): void
    {
        $html = $this->cetak()->getContent();

        // Jasa 1.000.000 (5.1) → Barang & Jasa; modal 5.000.000 (5.2) → Modal.
        $this->assertStringContainsString('<td class="num">1.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num">5.000.000</td>', $html);
        // Baris Jumlah: barang & jasa 1.000.000, modal 5.000.000, total 6.000.000.
        $this->assertStringContainsString('<td class="num">6.000.000</td>', $html);
    }

    /** Kode program/sub program/kegiatan dipecah dari kode_kegiatan. */
    public function test_rka_22_memecah_kode_kegiatan_per_kolom(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('<td>03</td>', $html);
        $this->assertStringContainsString('<td>01</td>', $html);
        $this->assertStringContainsString('5.1.02.01.01.0052', $html);
        $this->assertStringContainsString('5.2.02.05.01.0005', $html);
    }

    /** Kaki halaman & tanda tangan. */
    public function test_rka_22_berkaki_halaman_dan_bertanda_tangan(): void
    {
        $this->cetak()
            ->assertSee('RKAS Unit Kerja 2.2 - NPSN : 40309999', false)
            ->assertSee('Mengetahui,')
            ->assertSee('Nawawi Hamzah')
            ->assertSee('NIP : 197006202004111001');
    }

    /** Tanpa rincian lembar tetap terbuka (tidak 500). */
    public function test_rka_22_tanpa_rincian_tetap_terbuka(): void
    {
        RkasDetail::query()->delete();
        $this->header->delete();

        $this->cetak()->assertSee('Belum ada rincian RKAS untuk tahun ini.');
    }

    /** Varian ringkas (triwulan/tahap) tidak terpengaruh. */
    public function test_varian_ringkas_tetap_jalan(): void
    {
        $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rka-unit'))
            ->assertOk()
            ->assertSee('Rincian Anggran Pendapatan dan Belanja')
            ->assertDontSee('Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan');
    }
}
