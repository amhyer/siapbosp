<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\RkasDetail;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\User;
use App\Services\JuknisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cek Juknis BOSP 2026 (Permendikdasmen No 8/2026 Pasal 42) — batas
 * komponen honor (maks), buku (min), dan sarpras (maks) dari total pagu.
 */
class CekJuknisTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private BosType $reguler;

    private Rekening $rekHonor;

    private Rekening $rekBuku;

    private Rekening $rekSarpras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000009', 'nama' => 'SD Negeri Uji Juknis', 'jenjang' => 'SD',
            'sifat' => 'negeri', 'kecamatan' => 'Mamajang',
        ]);
        $this->operator = User::create([
            'name' => 'Operator Juknis', 'email' => 'juknis@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
        $this->reguler = BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        Allocation::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2026,
            'bos_type_id' => $this->reguler->id, 'nilai' => 100000000,
        ]);
        $this->rekHonor = Rekening::firstOrCreate(['kode' => '5.1.02.02.01.0013'], ['nama' => 'Honorarium Guru', 'tipe' => 'belanja']);
        $this->rekBuku = Rekening::firstOrCreate(['kode' => '5.1.02.03.05.0001'], ['nama' => 'Buku Umum', 'tipe' => 'belanja']);
        $this->rekSarpras = Rekening::firstOrCreate(['kode' => '5.1.02.03.03.0001'], ['nama' => 'Pemeliharaan Bangunan', 'tipe' => 'belanja']);
    }

    private function buatRkas(array $baris): RkasHeader
    {
        $h = RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2026, 'revisi_ke' => 1,
            'status' => 'disetujui',
        ]);
        foreach ($baris as $b) {
            RkasDetail::create([
                'rkas_header_id' => $h->id, 'rekening_id' => $b['rekening']->id,
                'kode_kegiatan' => $b['kegiatan'] ?? null, 'uraian' => $b['uraian'] ?? 'Item',
                'satuan' => 'paket', 'banyak' => 1, 'harga_satuan' => $b['nilai'], 'jumlah' => $b['nilai'],
                'sumber_dana' => 'BOS Reguler',
            ]);
        }

        return $h;
    }

    private function aturan(array $hasil, string $kunci): array
    {
        foreach ($hasil['aturan'] as $a) {
            if ($a['kunci'] === $kunci) {
                return $a;
            }
        }
        $this->fail('Aturan '.$kunci.' tak ditemukan.');
    }

    public function test_honor_negeri_dilampaui_diatas_20_persen(): void
    {
        $h = $this->buatRkas([
            ['rekening' => $this->rekHonor, 'kegiatan' => '07.12.01.', 'nilai' => 30000000],
        ]);
        $hasil = JuknisService::cek($h, $this->sekolah, 2026);

        $honor = $this->aturan($hasil, 'honor');
        $this->assertSame('maks', $honor['jenis']);
        $this->assertSame(20, $honor['batas_persen']);
        $this->assertSame(20000000, $honor['batas_nominal']);
        $this->assertSame(30000000, $honor['anggaran']);
        $this->assertSame(30.0, $honor['persen']);
        $this->assertSame('terlampaui', $honor['status']);
    }

    public function test_honor_swasta_batas_40_persen(): void
    {
        $this->sekolah->update(['sifat' => 'swasta']);
        $h = $this->buatRkas([
            ['rekening' => $this->rekHonor, 'kegiatan' => '07.12.01.', 'nilai' => 30000000],
        ]);
        $hasil = JuknisService::cek($h, $this->sekolah->fresh(), 2026);

        $honor = $this->aturan($hasil, 'honor');
        $this->assertSame(40, $honor['batas_persen']);
        $this->assertSame(40000000, $honor['batas_nominal']);
        $this->assertSame('aman', $honor['status']);
        $this->assertSame(10000000, $honor['sisa']);
    }

    public function test_buku_dasmen_kurang_dibawah_10_persen(): void
    {
        $h = $this->buatRkas([
            ['rekening' => $this->rekBuku, 'kegiatan' => '05.02.03.', 'nilai' => 5000000],
        ]);
        $hasil = JuknisService::cek($h, $this->sekolah, 2026);

        $buku = $this->aturan($hasil, 'buku');
        $this->assertSame('min', $buku['jenis']);
        $this->assertSame(10, $buku['batas_persen']);
        $this->assertSame('kurang', $buku['status']);
        $this->assertSame(5000000, $buku['sisa']);
    }

    public function test_buku_paud_minimal_lima_persen_terpenuhi(): void
    {
        $this->sekolah->update(['jenjang' => 'TK']);
        $h = $this->buatRkas([
            ['rekening' => $this->rekBuku, 'kegiatan' => '05.02.03.', 'nilai' => 5000000],
        ]);
        $hasil = JuknisService::cek($h, $this->sekolah->fresh(), 2026);

        $buku = $this->aturan($hasil, 'buku');
        $this->assertSame(5, $buku['batas_persen']);
        $this->assertSame('aman', $buku['status']);
    }

    public function test_sarpras_dilampaui_diatas_20_persen(): void
    {
        $h = $this->buatRkas([
            ['rekening' => $this->rekSarpras, 'kegiatan' => '05.08.01.', 'nilai' => 25000000],
        ]);
        $hasil = JuknisService::cek($h, $this->sekolah, 2026);

        $sarpras = $this->aturan($hasil, 'sarpras');
        $this->assertSame(20, $sarpras['batas_persen']);
        $this->assertSame('terlampaui', $sarpras['status']);
    }

    public function test_baris_tanpa_kode_kegiatan_diperiksa_lewat_rekening(): void
    {
        $h = $this->buatRkas([
            ['rekening' => $this->rekHonor, 'nilai' => 10000000],
        ]);
        $hasil = JuknisService::cek($h, $this->sekolah, 2026);

        $this->assertSame(10000000, $this->aturan($hasil, 'honor')['anggaran']);
        $this->assertStringContainsString('tanpa kode kegiatan', implode(' ', $hasil['catatan']));
    }

    public function test_tanpa_pagu_ditandai_tidak_bisa_dicek(): void
    {
        Allocation::where('school_id', $this->sekolah->id)->delete();
        $h = $this->buatRkas([
            ['rekening' => $this->rekHonor, 'kegiatan' => '07.12.01.', 'nilai' => 10000000],
        ]);
        $hasil = JuknisService::cek($h, $this->sekolah, 2026);

        $this->assertSame('tak_tercek', $this->aturan($hasil, 'honor')['status']);
        $this->assertStringContainsString('Pagu belum terisi', implode(' ', $hasil['catatan']));
    }

    public function test_halaman_rkas_menampilkan_kartu_juknis(): void
    {
        $this->buatRkas([
            ['rekening' => $this->rekHonor, 'kegiatan' => '07.12.01.', 'nilai' => 30000000],
        ]);

        $this->actingAs($this->operator)
            ->get('/rkas')
            ->assertOk()
            ->assertSee('Cek Juknis BOSP 2026')
            ->assertSee('Komponen Honor')
            ->assertSee('Terlampaui');
    }
}
