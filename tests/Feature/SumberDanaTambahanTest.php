<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\SumberDanaSekolah;
use App\Models\User;
use App\Services\ArkasImportService;
use App\Services\TarikArkasService;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Tab ke-6 "Sumber Dana Tambahan" di Penganggaran — cabang desain Q1 = ya dan
 * Q3 = ya (docs/UJI-ARKAS-LAPTOP-SUMBER-DANA.md §6):
 *
 * 1. importer membaca sheet `Ref_Sumber_Dana` apa adanya ke `sumber_dana_sekolah`
 *    (defensif pada nama kolom dan baris kosong);
 * 2. tab menampilkan chip nama sumber (hanya baris `is_sumber_dana_tambahan`)
 *    dan menyaring dokumen per nama lewat `?sumber_nama=`;
 * 3. bila daftar kosong, kartu menampilkan CTA "Tarik Data ARKAS" yang
 *    memakai TarikArkasController yang sudah ada (form hanya dipasang di
 *    aplikasi portabel — di server pusat tombol mati tidak boleh ada).
 */
class SumberDanaTambahanTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    /** Berkas semu untuk memalsukan mode portabel — dibersihkan di tearDown. */
    private array $fileSemu = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterSeeder::class);

        // NPSN sengaja sama dengan fixture tests/Fixtures/arkas-mini.xlsx.
        $this->sekolah = School::create([
            'npsn' => '90000001', 'nama' => 'SD Sumber Tambahan', 'jenjang' => 'SD',
            'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->operator = User::create([
            'name' => 'Operator Tambahan', 'email' => 'tambahan@tes.test',
            'password' => 'password', 'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);

        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        Allocation::withoutGlobalScope('school')->create([
            'school_id' => $this->sekolah->id, 'tahun' => 2026,
            'bos_type_id' => $bos->id, 'nilai' => 100_000_000,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->fileSemu as $f) {
            @unlink($f);
        }
        $this->fileSemu = [];
        @rmdir(base_path('php'));
        parent::tearDown();
    }

    /** Impor fixture arkas-mini.xlsx (memuat sheet Ref_Sumber_Dana). */
    private function impor(): array
    {
        return ArkasImportService::import(
            base_path('tests/Fixtures/arkas-mini.xlsx'), $this->sekolah->id, $this->operator->id, 2025);
    }

    /** Buka halaman Penganggaran pada tab tambahan (tahun aktif 2026). */
    private function halaman(array $query): string
    {
        return $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get(route('rkas.index', $query))
            ->assertOk()
            ->getContent();
    }

    private function buatDokumen(string $sumber, int $tahun = 2026): RkasHeader
    {
        $h = RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => $tahun, 'revisi_ke' => 1,
            'status' => 'draft', 'sumber_dana' => $sumber, 'jenis_rkas' => 'murni',
        ]);
        $h->details()->create([
            'rekening_id' => Rekening::where('kode', '5.1.02.01.01.0024')->firstOrFail()->id,
            'kode_kegiatan' => '06.05', 'uraian' => 'Kegiatan '.$sumber, 'satuan' => 'paket',
            'banyak' => 1, 'harga_satuan' => 1_000_000, 'jumlah' => 1_000_000,
            'sumber_dana' => $sumber,
        ]);

        return $h;
    }

    /** Pasang berkas semu agar TarikArkasService::tersedia() = true (mode portabel). */
    private function palsukanPortabel(): void
    {
        foreach (['php/php.exe', 'agen/TarikArkas/node.exe', 'agen/TarikArkas/tarik.cjs'] as $p) {
            $path = base_path($p);
            if (! is_file($path)) {
                @mkdir(dirname($path), 0755, true);
                touch($path);
                $this->fileSemu[] = $path;
            }
        }
    }

    /** Sheet Ref_Sumber_Dana masuk ke tabel per sekolah, idempoten. */
    public function test_impor_membaca_ref_sumber_dana_dan_idempoten(): void
    {
        $lap = $this->impor();

        $this->assertSame(3, $lap['dibuat']['sumber_dana']);
        $baris = SumberDanaSekolah::withoutGlobalScope('school')
            ->where('school_id', $this->sekolah->id)->get();
        $this->assertCount(3, $baris);

        $banpem = $baris->firstWhere('nama_sumber_dana', 'Banpem Keterampilan Menulis pada Sekolah Dasar');
        $this->assertNotNull($banpem, 'Baris ber-flag tambahan ikut terbawa.');
        $this->assertSame('4.3.1.1005', $banpem->kode);
        $this->assertSame('1005', (string) $banpem->id_ref_sumber_dana);
        $this->assertSame('Banpem Keterampilan Menulis', $banpem->alias);
        $this->assertTrue($banpem->is_sumber_dana_tambahan);
        $this->assertFalse($baris->firstWhere('nama_sumber_dana', 'BOS Reguler')->is_sumber_dana_tambahan);

        // Impor ulang: tidak ada yang dobel — semua baris diperbarui.
        $ulang = $this->impor();
        $this->assertSame(0, $ulang['dibuat']['sumber_dana']);
        $this->assertSame(3, $ulang['diperbarui']['sumber_dana']);
        $this->assertSame(3, SumberDanaSekolah::withoutGlobalScope('school')
            ->where('school_id', $this->sekolah->id)->count());
    }

    /** Versi ARKAS tanpa sheet Ref_Sumber_Dana: impor tetap jalan, daftar lama dipertahankan. */
    public function test_impor_toleran_bila_sheet_ref_sumber_dana_tidak_ada(): void
    {
        SumberDanaSekolah::create([
            'school_id' => $this->sekolah->id, 'id_ref_sumber_dana' => '1005',
            'kode' => '4.3.1.1005', 'nama_sumber_dana' => 'Banpem Keterampilan Menulis pada Sekolah Dasar',
            'is_sumber_dana_tambahan' => true,
        ]);

        $wb = IOFactory::load(base_path('tests/Fixtures/arkas-mini.xlsx'));
        $idx = array_search('Ref_Sumber_Dana', $wb->getSheetNames(), true);
        $this->assertNotFalse($idx);
        $wb->removeSheetByIndex($idx);
        $berkas = tempnam(sys_get_temp_dir(), 'arkas').'.xlsx';
        (new Xlsx($wb))->save($berkas);

        try {
            $lap = ArkasImportService::import($berkas, $this->sekolah->id, $this->operator->id, 2025);
        } finally {
            @unlink($berkas);
        }

        $this->assertSame(0, $lap['dibuat']['sumber_dana']);
        $this->assertSame(1, SumberDanaSekolah::withoutGlobalScope('school')->count(),
            'Baris lama tidak boleh terhapus walau sheet tidak ikut terkirim.');
    }

    /** Defensif: kolom id diganti nama lain → baris terbaca lewat nama; baris tanpa nama dilewati. */
    public function test_impor_defensif_kolom_dan_baris(): void
    {
        // a) Kolom id_ref_sumber_dana tidak dikenal — tetap masuk, kunci jatuh ke nama.
        $wb = IOFactory::load(base_path('tests/Fixtures/arkas-mini.xlsx'));
        $wb->getSheetByName('Ref_Sumber_Dana')->setCellValue('A2', 'kode_arkas_lama');
        $tanpaId = tempnam(sys_get_temp_dir(), 'arkas').'.xlsx';
        (new Xlsx($wb))->save($tanpaId);
        try {
            ArkasImportService::import($tanpaId, $this->sekolah->id, $this->operator->id, 2025);
        } finally {
            @unlink($tanpaId);
        }

        $baris = SumberDanaSekolah::withoutGlobalScope('school')->get();
        $this->assertCount(3, $baris);
        $this->assertNull($baris->first()->id_ref_sumber_dana);
        $this->assertTrue($baris->contains(
            fn ($b) => $b->nama_sumber_dana === 'BOS Reguler' && $b->is_sumber_dana_tambahan === false));
    }

    public function test_impor_melewati_baris_tanpa_nama_sumber_dana(): void
    {
        $wb = IOFactory::load(base_path('tests/Fixtures/arkas-mini.xlsx'));
        $wb->getSheetByName('Ref_Sumber_Dana')->setCellValue('C3', ''); // baris "BOS Reguler"
        $tanpaNama = tempnam(sys_get_temp_dir(), 'arkas').'.xlsx';
        (new Xlsx($wb))->save($tanpaNama);

        try {
            $lap = ArkasImportService::import($tanpaNama, $this->sekolah->id, $this->operator->id, 2025);
        } finally {
            @unlink($tanpaNama);
        }

        $this->assertSame(2, $lap['dibuat']['sumber_dana']);
        $this->assertSame(2, SumberDanaSekolah::withoutGlobalScope('school')->count());
    }

    /** Chip hanya untuk sumber ber-flag tambahan; pilihan ?sumber_nama= menyaring dokumen. */
    public function test_tab_tambahan_menampilkan_chip_dan_menyaring_per_nama(): void
    {
        SumberDanaSekolah::create([
            'school_id' => $this->sekolah->id, 'id_ref_sumber_dana' => '1005',
            'kode' => '4.3.1.1005', 'nama_sumber_dana' => 'Banpem Keterampilan Menulis pada Sekolah Dasar',
            'is_sumber_dana_tambahan' => true,
        ]);
        SumberDanaSekolah::create([
            'school_id' => $this->sekolah->id, 'id_ref_sumber_dana' => '1',
            'kode' => '4.3.1.01.', 'nama_sumber_dana' => 'BOS Reguler',
        ]);

        // Tahun berbeda per dokumen: daftar melipat satu baris per tahun
        // (revisi dilipat), jadi pembanding diletakkan di tahun lain.
        $this->buatDokumen('Banpem Keterampilan Menulis pada Sekolah Dasar', 2026);
        $this->buatDokumen('BOSP Afirmasi', 2025);
        $this->buatDokumen('BOS Reguler', 2026);

        // Tanpa pilihan: dokumen tab tambahan tampil semua, chip hanya yang tambahan.
        $semua = $this->halaman(['tab' => 'tambahan']);
        $this->assertStringContainsString('RKAS Banpem Keterampilan Menulis pada Sekolah Dasar 2026', $semua);
        $this->assertStringContainsString('RKAS BOSP Afirmasi 2025', $semua);
        $this->assertStringNotContainsString('RKAS BOS Reguler 2026', $semua,
            'Dokumen ber-sumber standar tidak termasuk tab tambahan.');
        $this->assertStringContainsString('sumber_nama=', $semua, 'Chip penyaring nama sumber tidak ada.');
        $this->assertStringContainsString('title="Kode 4.3.1.1005"', $semua);
        $this->assertStringNotContainsString('title="Kode 4.3.1.01."', $semua,
            'Sumber standar tidak boleh menjadi chip tab tambahan.');

        // Pilih satu nama → hanya dokumen dengan sumber itu.
        $pilih = $this->halaman(['tab' => 'tambahan',
            'sumber_nama' => 'Banpem Keterampilan Menulis pada Sekolah Dasar']);
        $this->assertStringContainsString('RKAS Banpem Keterampilan Menulis pada Sekolah Dasar 2026', $pilih);
        $this->assertStringNotContainsString('RKAS BOSP Afirmasi 2025', $pilih);

        // Nama di luar daftar diabaikan (whitelist) — daftar lengkap tetap tampil.
        $ngawur = $this->halaman(['tab' => 'tambahan', 'sumber_nama' => 'Dana Ngawur']);
        $this->assertStringContainsString('RKAS BOSP Afirmasi 2025', $ngawur);
        $this->assertStringContainsString('RKAS Banpem Keterampilan Menulis pada Sekolah Dasar 2026', $ngawur);
    }

    /** Hasil impor ARKAS: nama sumber ada di baris detail, header masih nama default. */
    public function test_dokumen_impor_terbaca_lewat_sumber_di_detail(): void
    {
        SumberDanaSekolah::create([
            'school_id' => $this->sekolah->id, 'id_ref_sumber_dana' => '1005',
            'kode' => '4.3.1.1005', 'nama_sumber_dana' => 'Banpem Keterampilan Menulis pada Sekolah Dasar',
            'is_sumber_dana_tambahan' => true,
        ]);

        $h = RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2026, 'revisi_ke' => 1,
            'status' => 'draft', 'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni',
        ]);
        $h->details()->create([
            'rekening_id' => Rekening::where('kode', '5.1.02.01.01.0024')->firstOrFail()->id,
            'kode_kegiatan' => '06.33', 'uraian' => 'Banpem menulis', 'satuan' => 'paket',
            'banyak' => 1, 'harga_satuan' => 1_000_000, 'jumlah' => 1_000_000,
            'sumber_dana' => 'Banpem Keterampilan Menulis pada Sekolah Dasar',
        ]);

        $semua = $this->halaman(['tab' => 'tambahan']);
        $this->assertStringContainsString('RKAS BOS Reguler 2026', $semua,
            'Dokumen dengan nama sumber di DETAIL harus ikut tampil di tab tambahan.');

        $pilih = $this->halaman(['tab' => 'tambahan',
            'sumber_nama' => 'Banpem Keterampilan Menulis pada Sekolah Dasar']);
        $this->assertStringContainsString('RKAS BOS Reguler 2026', $pilih);
    }

    /** Daftar kosong (bukan aplikasi portabel): CTA muncul, tombol tarik TIDAK dipasang. */
    public function test_cta_tampil_bila_daftar_kosong(): void
    {
        $html = $this->halaman(['tab' => 'tambahan']);

        $this->assertStringContainsString('belum ada', $html);
        $this->assertStringContainsString('Tarik Data ARKAS', $html);
        $this->assertStringContainsString(route('impor-arkas.form'), $html);
        $this->assertStringNotContainsString('action="'.route('tarik-arkas.tarik').'"', $html,
            'Di server pusat form tarik tidak boleh dipasang (tombol mati).');
    }

    /** Aplikasi portabel: CTA memasang form TarikArkasController; daftar ada → tombol Perbarui. */
    public function test_cta_portable_memasang_form_tarik_data_arkas(): void
    {
        $this->palsukanPortabel();
        $this->assertTrue(TarikArkasService::tersedia(), 'Prasyarat mode portabel gagal dipalsukan.');

        $kosong = $this->halaman(['tab' => 'tambahan']);
        $this->assertStringContainsString('action="'.route('tarik-arkas.tarik').'"', $kosong);
        $this->assertStringContainsString('Tarik Data ARKAS', $kosong);

        SumberDanaSekolah::create([
            'school_id' => $this->sekolah->id, 'id_ref_sumber_dana' => '1005',
            'kode' => '4.3.1.1005', 'nama_sumber_dana' => 'Banpem Keterampilan Menulis pada Sekolah Dasar',
            'is_sumber_dana_tambahan' => true,
        ]);
        $ada = $this->halaman(['tab' => 'tambahan']);
        $this->assertStringContainsString('action="'.route('tarik-arkas.tarik').'"', $ada);
        $this->assertStringContainsString('Perbarui dari ARKAS', $ada);
        $this->assertStringContainsString('Banpem Keterampilan Menulis pada Sekolah Dasar', $ada);
    }
}
