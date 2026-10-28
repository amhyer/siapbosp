<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kertas Kerja RAPBS per Tahap — dokumen cetak baru, bentuknya diambil dari
 * rapbs-tahap-output.pdf ARKAS 4.3.1:
 *   judul "KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP",
 *   "TAHUN ANGGARAN : …", blok A. PENERIMAAN per sumber dana, blok B. BELANJA
 *   berbentuk pohon program → kegiatan → sub-kegiatan → rincian, kolom per tahap,
 *   lalu baris Jumlah; kaki halaman "Kertas Kerja perTahap - NPSN : …".
 */
class RapbsCetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $op;

    private RkasHeader $header;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40305555', 'nama' => 'SDN Uji RAPBS', 'jenjang' => 'SD',
            'desa' => 'Jl. RAPBS No. 2', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan', 'status_sekolah' => 'negeri', 'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000]);
        $this->op = User::create(['name' => 'Bendahara RAPBS', 'email' => 'rapbs@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $this->school->id]);
        $this->actingAs($this->op);

        $bos = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Allocation::create(['school_id' => $this->school->id, 'tahun' => 2026, 'bos_type_id' => $bos->id,
            'stage_id' => null, 'nilai' => 100_000_000]);

        Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Stage::create(['tahun' => 2026, 'nama' => 'Tahap 2', 'urutan' => 2, 'is_open' => true]);

        $this->header = RkasHeader::create(['school_id' => $this->school->id, 'tahun' => 2026,
            'revisi_ke' => 1, 'status' => 'draft', 'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni']);

        $honor = Rekening::firstOrCreate(['kode' => '5.1.02.02.01.0013'], ['nama' => 'Honorarium Guru', 'tipe' => 'belanja']);
        $buku = Rekening::firstOrCreate(['kode' => '5.1.02.03.05.0001'], ['nama' => 'Buku Teks', 'tipe' => 'belanja']);

        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $honor->id,
            'kode_kegiatan' => '07.12.01.', 'uraian' => 'Honor Guru Kelas', 'volume' => 3,
            'satuan' => 'orang / bulan', 'harga_satuan' => 2_000_000, 'jumlah' => 6_000_000]);
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $buku->id,
            'kode_kegiatan' => '05.02.03.', 'uraian' => 'Buku Teks Utama', 'volume' => 80,
            'satuan' => 'exemplar', 'harga_satuan' => 50_000, 'jumlah' => 4_000_000]);
    }

    private function cetak()
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rapbs-cetak'))
            ->assertOk();
    }

    /** Judul, periode, dan blok A/B seperti lembar ARKAS. */
    public function test_rapbs_berkop_dan_berblok_seperti_arkas(): void
    {
        $r = $this->cetak();

        $r->assertSee('KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP')
            ->assertSee('TAHUN ANGGARAN : 2026')
            ->assertSee('A. PENERIMAAN')
            ->assertSee('B. BELANJA')
            ->assertSee('Sumber Dana :')
            ->assertSee('Total Penerimaan')
            ->assertSee('NPSN : 40305555')
            ->assertSee('Tahap : Tahap 1 dan Tahap 2');

        // Kaki halaman ARKAS.
        $r->assertSee('content: "Kertas Kerja perTahap - NPSN : 40305555, Nama Sekolah : ', false);
    }

    /** Penerimaan memakai kode sumber dana resmi ARKAS. */
    public function test_rapbs_mencetak_sumber_dana_dengan_kode_arkas(): void
    {
        $this->cetak()
            ->assertSee('4.3.1.01.')
            ->assertSee('BOS Reguler')
            ->assertSee('100.000.000');
    }

    /** Belanja berbentuk pohon: program, kegiatan, sub-kegiatan, lalu rincian. */
    public function test_rapbs_membentuk_pohon_kode_kegiatan(): void
    {
        $html = $this->cetak()->getContent();

        // Ringkasan bertingkat dari kode kegiatan '07.12.01.'
        foreach (['07.', '07.12.', '07.12.01.'] as $kode) {
            $this->assertStringContainsString('>'.$kode.'</td>', $html, 'Baris ringkasan hilang: '.$kode);
        }
        // Rincian: rekening, uraian, volume, satuan, tarif.
        $this->assertStringContainsString('5.1.02.02.01.0013', $html);
        $this->assertStringContainsString('Honor Guru Kelas', $html);
        $this->assertStringContainsString('Buku Teks Utama', $html);
        $this->assertStringContainsString('orang / bulan', $html);
        $this->assertStringContainsString('<td class="num">2.000.000</td>', $html);
    }

    /** Jumlah dan pembagian per tahap dihitung, bukan dikarang di view. */
    public function test_rapbs_menjumlah_dan_membagi_per_tahap(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('>Jumlah</td>', $html);
        // Total belanja 6.000.000 + 4.000.000.
        $this->assertStringContainsString('Jumlah Belanja : <b>Rp. 10.000.000</b>', $html);
        // Tanpa periode_volume: dibagi rata ke 2 tahap → 3.000.000 + 2.000.000.
        $this->assertStringContainsString('<td class="num">3.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num">2.000.000</td>', $html);
    }

    /** Tanpa RKAS halaman tetap terbuka (tidak 500) dan menjelaskan keadaannya. */
    public function test_rapbs_tanpa_rkas_tetap_terbuka(): void
    {
        RkasDetail::query()->delete();
        $this->header->delete();

        $this->cetak()->assertSee('Belum ada rincian RKAS untuk tahun ini.');
    }

    /** Dokumen bisa dijangkau dari layar Rincian RKAS per Tahap. */
    public function test_tautan_dari_layar_rkas_tahap(): void
    {
        $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.rkas-tahap'))
            ->assertOk()
            ->assertSee(route('laporan-arkas.rapbs-cetak'), false);
    }
}
