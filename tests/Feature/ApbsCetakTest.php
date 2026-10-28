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
 * APBS / RKAS seluruh sumber dana — bentuk dari apbs-all-output.pdf:
 *   A. PENERIMAAN (No. / Kode Penerimaan / Jumlah) + Total Penerimaan,
 *   B. BELANJA lima tingkat baris dengan kolom `Sumber Dana dan Alokasi Anggaran`
 *   = 6 kelompok (BOSP REGULER · BOSP DAERAH · AFIRMASI · SiLPA · BOSP LAINNYA · KINERJA)
 *   x (Belanja Operasi · Belanja Modal), ditutup baris `Jumlah` dan 3 tanda tangan.
 */
class ApbsCetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private RkasHeader $header;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40308888', 'nama' => 'SDN Uji APBS', 'jenjang' => 'SD',
            'desa' => 'Jl. APBS No. 1, Kec. Makasar', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan', 'status_sekolah' => 'negeri', 'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000]);

        $this->actingAs(User::create(['name' => 'Bendahara APBS', 'email' => 'apbs@sdn.test',
            'password' => 'secret123', 'role' => 'operator', 'school_id' => $this->school->id]));

        Penandatangan::create(['nip' => '198112072007012013', 'nama' => 'Andi Windayani',
            'jabatan' => 'Kepala Sekolah']);
        Penandatangan::create(['nip' => null, 'nama' => 'Dila Mayasari', 'jabatan' => 'Komite Sekolah']);
        Penandatangan::create(['nip' => '199708122024212017', 'nama' => 'Lilis Agustina',
            'jabatan' => 'Bendahara Sekolah']);

        $tahap1 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $bos = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Allocation::create(['school_id' => $this->school->id, 'tahun' => 2026, 'bos_type_id' => $bos->id,
            'stage_id' => $tahap1->id, 'nilai' => 100_000_000]);

        foreach ([['03.', 'Standar Proses', 1], ['03.01.', 'Penerimaan Murid baru', 2],
            ['03.01.01.', 'Pelaksanaan Pendaftaran Murid Baru (PMB)', 3],
            ['05.', 'Standar Sarana dan Prasarana', 1], ['05.08.', 'Pemeliharaan Sarana dan Prasarana Sekolah', 2],
            ['05.08.01.', 'Pemeliharaan Prasarana Lahan, Bangunan dan Ruang', 3]] as [$kode, $uraian, $level]) {
            ArkasKode::create(['school_id' => $this->school->id, 'tahun' => 2026,
                'kode' => $kode, 'uraian' => $uraian, 'level' => $level]);
        }

        $this->header = RkasHeader::create(['school_id' => $this->school->id, 'tahun' => 2026,
            'revisi_ke' => 1, 'status' => 'draft', 'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni']);

        $rapat = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0052'],
            ['nama' => 'Belanja Makanan dan Minuman Rapat', 'tipe' => 'belanja']);
        $komputer = Rekening::firstOrCreate(['kode' => '5.2.02.05.01.0005'],
            ['nama' => 'Belanja Modal Komputer', 'tipe' => 'belanja']);

        // Dua rincian pada rekening yang sama (nomor 001 & 002) + satu belanja modal
        // yang sumber dananya berbeda (Afirmasi).
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $rapat->id,
            'kode_kegiatan' => '03.01.01.', 'uraian' => 'Konsumsi rapat panitia', 'banyak' => 2,
            'satuan' => 'paket', 'harga_satuan' => 2_700_000, 'jumlah' => 5_400_000,
            'sumber_dana' => 'BOS Reguler']);
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $rapat->id,
            'kode_kegiatan' => '03.01.01.', 'uraian' => 'Konsumsi tambahan', 'banyak' => 1,
            'satuan' => 'paket', 'harga_satuan' => 1_000_000, 'jumlah' => 1_000_000,
            'sumber_dana' => 'BOS Reguler']);
        RkasDetail::create(['rkas_header_id' => $this->header->id, 'rekening_id' => $komputer->id,
            'kode_kegiatan' => '05.08.01.', 'uraian' => 'Komputer ruang guru', 'banyak' => 1,
            'satuan' => 'unit', 'harga_satuan' => 10_000_000, 'jumlah' => 10_000_000,
            'sumber_dana' => 'BOS Afirmasi']);
    }

    private function cetak()
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('laporan-arkas.apbs'))
            ->assertOk();
    }

    /** Judul, kop identitas, dan kedua bagian A/B. */
    public function test_apbs_berjudul_dan_berkop(): void
    {
        $this->cetak()
            ->assertSee('RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)')
            ->assertSee('TAHUN ANGGARAN : 2026')
            ->assertSee('Nama Sekolah')
            ->assertSee('SDN Uji APBS')
            ->assertSee('Kabupaten')
            ->assertSee('Kota Makassar')
            ->assertSee('Prov. Sulawesi Selatan')
            ->assertSee(': 40308888')
            ->assertSee('A. PENERIMAAN')
            ->assertSee('B. BELANJA');
    }

    /** A. Penerimaan: kode 4.3.1.01. + label BOSP Reguler + Total Penerimaan. */
    public function test_apbs_penerimaan_memakai_kode_sumber_dana(): void
    {
        $this->cetak()
            ->assertSee('Kode Penerimaan')
            ->assertSee('4.3.1.01.', false)
            ->assertSee('BOSP Reguler')
            ->assertSee('Total Penerimaan')
            ->assertSee('<td class="num">100.000.000</td>', false);
    }

    /** Kepala kolom 6 kelompok dana x Belanja Operasi/Modal. */
    public function test_apbs_berkepala_kolom_enam_kelompok_dana(): void
    {
        $this->cetak()
            ->assertSee('Sumber Dana dan Alokasi Anggaran')
            ->assertSee('BOSP REGULER')
            ->assertSee('BOSP DAERAH')
            ->assertSee('AFIRMASI')
            ->assertSee('SiLPA')
            ->assertSee('BOSP LAINNYA')
            ->assertSee('KINERJA')
            ->assertSee('Belanja<br>Operasi', false)
            ->assertSee('Belanja<br>Modal', false)
            ->assertSee('Kode<br>Kegiatan', false)
            ->assertSee('Kode Rekening');
    }

    /** Lima tingkat baris + nomor urut menaik. */
    public function test_apbs_membentuk_pohon_lima_tingkat(): void
    {
        $html = $this->cetak()->getContent();

        foreach (['Standar Proses', 'Penerimaan Murid baru',
            'Pelaksanaan Pendaftaran Murid Baru (PMB)',
            'Belanja Makanan dan Minuman Rapat', 'Pemeliharaan Prasarana Lahan, Bangunan dan Ruang'] as $teks) {
            $this->assertStringContainsString($teks, $html, 'Baris hilang: '.$teks);
        }

        // Kode kegiatan & kode rekening terpisah pada kolomnya.
        $this->assertStringContainsString('03.01.01.', $html);
        $this->assertStringContainsString('5.1.02.01.01.0052', $html);
        $this->assertStringContainsString('5.2.02.05.01.0005', $html);
    }

    /** Nomor rincian 001/002 berulang per blok rekening. */
    public function test_apbs_memberi_nomor_rincian_per_blok_rekening(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('001. Konsumsi rapat panitia', $html);
        $this->assertStringContainsString('002. Konsumsi tambahan', $html);
        $this->assertStringContainsString('001. Komputer ruang guru', $html);
    }

    /** Sumber dana & jenis belanja mengisi kolom yang benar. */
    public function test_apbs_mengisi_kolom_kelompok_dana_dan_jenis_belanja(): void
    {
        $html = $this->cetak()->getContent();

        // Program 03 = 6.400.000 (dua rincian operasi BOSP Reguler).
        $this->assertStringContainsString('<td class="num" style="font-weight:700">6.400.000</td>', $html);
        // Program 05 = 10.000.000 modal Afirmasi.
        $this->assertStringContainsString('<td class="num" style="font-weight:700">10.000.000</td>', $html);
        // Total: 16.400.000.
        $this->assertStringContainsString('<td class="num">16.400.000</td>', $html);
    }

    /** Baris Jumlah + kaki halaman + tiga tanda tangan. */
    public function test_apbs_ditutup_jumlah_dan_tiga_tanda_tangan(): void
    {
        $this->cetak()
            ->assertSee('Jumlah')
            ->assertSee('Mengetahui,')
            ->assertSee('Kepala Sekolah')
            ->assertSee('Komite Sekolah')
            ->assertSee('Bendahara Sekolah')
            ->assertSee('Andi Windayani')
            ->assertSee('Dila Mayasari')
            ->assertSee('Lilis Agustina')
            ->assertSee('NIP : 199708122024212017')
            ->assertSee('Kec. Makasar,', false)
            ->assertSee('RKAS - NPSN : 40308888', false);
    }

    /** Tanpa RKAS lembar tetap terbuka. */
    public function test_apbs_tanpa_rincian_tetap_terbuka(): void
    {
        RkasDetail::query()->delete();
        $this->header->delete();

        $this->cetak()->assertSee('Belum ada rincian RKAS untuk tahun ini.');
    }
}
