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
 * RKA / DPA (RKAS) Unit Kerja — bentuknya diambil dari rka-unit-output.pdf,
 * rka-unit-tahap-output.pdf, dan dpa-unit-tahap-output.pdf (ARKAS 4.3.1):
 *   "LEMBAR KERTAS KERJA" / "LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)",
 *   "UNIT KERJA", "PEMERINTAH …", "TAHUN ANGGARAN …", "Urusan Pemerintahan
 *   : 1.01 - PENDIDIKAN", "Organisasi : <NPSN> - <NAMA SEKOLAH>",
 *   "Rincian Anggran Pendapatan dan Belanja" (salah ketik ARKAS dipertahankan),
 *   subtotal akun 5 / 5.1 / 5.1.02 / 5.1.02.0x / 5.2 / …,
 *   lalu "Rencana Pelaksanaan Anggaran / Unit Kerja per Triwulan|Tahap".
 */
class RkaUnitCetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $op;

    private RkasHeader $header;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40305555', 'nama' => 'SDN Uji RKA', 'jenjang' => 'SD',
            'desa' => 'Jl. RKA No. 3', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan', 'status_sekolah' => 'negeri', 'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000]);
        $this->op = User::create(['name' => 'Bendahara RKA', 'email' => 'rka@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $this->school->id]);
        $this->actingAs($this->op);

        Penandatangan::create(['nip' => '197006202004111001', 'nama' => 'Nawawi Hamzah',
            'jabatan' => 'Kepala Sekolah']);

        $tahap1 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Stage::create(['tahun' => 2026, 'nama' => 'Tahap 2', 'urutan' => 2, 'is_open' => true]);

        $bos = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Allocation::create(['school_id' => $this->school->id, 'tahun' => 2026, 'bos_type_id' => $bos->id,
            'stage_id' => $tahap1->id, 'nilai' => 100_000_000]);

        $this->header = RkasHeader::create(['school_id' => $this->school->id, 'tahun' => 2026,
            'revisi_ke' => 1, 'status' => 'draft', 'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni']);

        $jasa = Rekening::firstOrCreate(['kode' => '5.1.02.02.01.0013'], ['nama' => 'Honorarium Guru', 'tipe' => 'belanja']);
        $pemeliharaan = Rekening::firstOrCreate(['kode' => '5.1.02.03.05.0001'], ['nama' => 'Buku Teks', 'tipe' => 'belanja']);

        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $jasa->id,
            'kode_kegiatan' => '07.12.01.', 'uraian' => 'Honor Guru Kelas', 'banyak' => 3,
            'satuan' => 'orang / bulan', 'harga_satuan' => 2_000_000, 'jumlah' => 6_000_000,
            'bulan_volumes' => json_encode(['3' => 3])]);
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $pemeliharaan->id,
            'kode_kegiatan' => '05.02.03.', 'uraian' => 'Buku Teks Utama', 'banyak' => 80,
            'satuan' => 'exemplar', 'harga_satuan' => 50_000, 'jumlah' => 4_000_000]);
    }

    private function cetak(array $query = [])
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rka-unit', $query))
            ->assertOk();
    }

    /** Kop lembar RKA untuk wali data (bukan ARKAS). */
    public function test_rka_unit_berkop_lembar_kertas_kerja(): void
    {
        $this->cetak()
            ->assertSee('LEMBAR KERTAS KERJA')
            ->assertSee('UNIT KERJA')
            ->assertSee('PEMERINTAH KOTA MAKASSAR')
            ->assertSee('TAHUN ANGGARAN 2026')
            ->assertSee('Urusan Pemerintahan')
            ->assertSee(': 1.01 - PENDIDIKAN')
            ->assertSee(': 40305555 - SDN UJI RKA')
            // Salah ketik "Anggran" memang dari ARKAS, jangan diperbaiki.
            ->assertSee('Rincian Anggran Pendapatan dan Belanja');
    }

    /** Subtotal akun dihitung dari prefix kode rekening sampai 4 segmen. */
    public function test_rka_unit_menjumlah_akun_sampai_empat_segmen(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('BELANJA JASA', $html);
        $this->assertStringContainsString('BELANJA PEMELIHARAAN', $html);
        $this->assertStringContainsString('5.1.02.02', $html);
        $this->assertStringContainsString('5.1.02.03', $html);
        // Jumlah pendapatan 100 juta, belanja 10 juta, defisit 90 juta.
        foreach (['JUMLAH PENDAPATAN', 'JUMLAH BELANJA', 'DEFISIT'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('<td class="num">100.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num">10.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num">90.000.000</td>', $html);
    }

    /** Bagian kedua: kolom I–IV untuk varian triwulan (baku). */
    public function test_rka_unit_triwulan_memakai_kolom_romawi(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('Rencana Pelaksanaan Anggaran', $html);
        $this->assertStringContainsString('Unit Kerja per Triwulan', $html);
        foreach (['>I</th>', '>II</th>', '>III</th>', '>IV</th>'] as $kolom) {
            $this->assertStringContainsString($kolom, $html);
        }
        foreach (['Pendapatan', 'Belanja Operasi', 'Belanja Modal',
            'Penerimaan Pembiayaan', 'Pengeluaran Pembiayaan'] as $baris) {
            $this->assertStringContainsString($baris, $html);
        }
    }

    /** Rencana belanja masuk triwulan sesuai bulan rencananya. */
    public function test_rka_unit_menaruh_belanja_di_triwulan_bulannya(): void
    {
        // Honor (bulan 3) + buku (fallback Januari) dua-duanya triwulan I.
        $this->cetak()
            ->assertSee('Unit Kerja per Triwulan')
            ->assertSee('<td class="num">10.000.000</td>', false)
            // Triwulan II–IV kosong.
            ->assertSee('<td class="num">0</td>', false);
    }

    /** varian=dpa memakai judul RKAS, isinya sama. */
    public function test_dpa_unit_memakai_judul_rkas(): void
    {
        $this->cetak(['varian' => 'dpa'])
            ->assertSee('LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)')
            ->assertDontSee('LEMBAR KERTAS KERJA')
            ->assertSee('Unit Kerja per Triwulan');
    }

    /** format=tahap mengganti kolom triwulan menjadi kolom tahap. */
    public function test_rka_unit_format_tahap(): void
    {
        $this->cetak(['format' => 'tahap'])
            ->assertSee('Unit Kerja per Tahap')
            ->assertDontSee('Unit Kerja per Triwulan')
            ->assertSee('<th class="num" style="width:6.5rem">1</th>', false)
            ->assertSee('<th class="num" style="width:6.5rem">2</th>', false);
    }

    /** Tanda tangan kepala sekolah + tempat/tanggal seperti lembar ARKAS. */
    public function test_rka_unit_menandatangani_kepala_sekolah(): void
    {
        $this->cetak()
            ->assertSee('Mengetahui,')
            ->assertSee('Kepala Sekolah')
            ->assertSee('Nawawi Hamzah')
            ->assertSee('NIP : 197006202004111001')
            ->assertSee('Kec. Makasar,');
    }

    /** Tanpa RKAS lembar tetap terbuka dan angka nol, bukan 500. */
    public function test_rka_unit_tanpa_rkas_tetap_terbuka(): void
    {
        RkasDetail::query()->delete();
        $this->header->delete();

        $this->cetak()
            ->assertSee('Rincian Anggran Pendapatan dan Belanja')
            ->assertSee('JUMLAH BELANJA');
    }
}
