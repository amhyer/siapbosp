<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\RkasHeader;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master sumber dana SiapBOSP vs `ref_sumber_dana` ARKAS Desktop 4.3.1.
 *
 * ARKAS punya 9 baris (8 sumber dana + "Saldo Awal"). Angka & nama di test ini
 * diambil apa adanya dari docs/arkas-ref/ref_sumber_dana.csv — kalau ARKAS
 * menambah/mengganti baris, test ini yang harus diperbarui lebih dulu.
 */
class SumberDanaMasterTest extends TestCase
{
    use RefreshDatabase;

    /** Nama resmi, urut kode rekening. */
    private const NAMA_ARKAS = [
        'BOS Reguler', 'BOS Daerah', 'Lainnya', 'BOS Afirmasi', 'BOS Kinerja',
        'SiLPA BOS Reguler', 'SiLPA BOS Afirmasi', 'SiLPA BOS Kinerja', 'Saldo Awal',
    ];

    public function test_daftar_sumber_dana_sama_dengan_ref_arkas(): void
    {
        $this->assertSame(self::NAMA_ARKAS, RkasHeader::SUMBER_DANA_ARKAS);
        $this->assertSame(
            array_column(RkasHeader::SUMBER_DANA_REF_ARKAS, 'nama'),
            RkasHeader::SUMBER_DANA_ARKAS
        );
        $this->assertSame(
            ['4.3.1.01.', '4.3.1.03.', '4.3.1.05.', '4.3.1.11', '4.3.1.12', '4.3.1.33.', '4.3.1.34', '4.3.1.35', '0'],
            array_column(RkasHeader::SUMBER_DANA_REF_ARKAS, 'kode')
        );
    }

    /** "Saldo Awal" bukan sumber dana yang boleh dipilih pada RKAS. */
    public function test_pilihan_form_tidak_memuat_saldo_awal(): void
    {
        $this->assertNotContains('Saldo Awal', RkasHeader::SUMBER_DANA_PILIHAN);
        $this->assertSame(8, count(RkasHeader::SUMBER_DANA_PILIHAN));
        $this->assertSame(
            array_values(array_diff(self::NAMA_ARKAS, ['Saldo Awal'])),
            RkasHeader::SUMBER_DANA_PILIHAN
        );
    }

    /** Seed master harus menyediakan satu BosType untuk tiap pilihan sumber dana. */
    public function test_seeder_menyediakan_jenis_bos_untuk_semua_pilihan(): void
    {
        $this->seed(MasterSeeder::class);

        $nama = BosType::pluck('nama', 'kode')->all();

        foreach (RkasHeader::SUMBER_DANA_PILIHAN as $sumber) {
            $this->assertContains($sumber, $nama,
                'BosType "'.$sumber.'" belum ada — filter tab sumber dana akan kosong.');
        }

        // Ejaan lama tidak boleh lagi dipakai sebagai nama baru, tetapi baris
        // lamanya (kode bosda/silpa) tetap ada supaya data lama tetap terbaca.
        $this->assertContains('BOS Daerah', $nama);
        $this->assertSame('BOS Daerah', $nama['bosda']);
        $this->assertSame('SiLPA BOS Reguler', $nama['silpa_reguler']);
        $this->assertSame('SiLPA BOS Afirmasi', $nama['silpa_afirmasi']);
    }

    /** SiLPA per sumber ikut tab sumber induknya — tidak ada tab SiLPA terpisah. */
    public function test_tab_memuat_jenis_silpa_per_sumber(): void
    {
        $this->seed(MasterSeeder::class);

        $reguler = BosType::whereIn('id', RkasHeader::bosTypeIds('reguler'))->pluck('kode')->all();
        $tambahan = BosType::whereIn('id', RkasHeader::bosTypeIds('tambahan'))->pluck('kode')->all();
        $silpaKinerja = BosType::whereIn('id', RkasHeader::bosTypeIds('silpa_kinerja'))->pluck('kode')->all();

        $this->assertContains('reguler', $reguler);
        $this->assertContains('silpa_reguler', $reguler);
        $this->assertContains('silpa_afirmasi', $tambahan);
        $this->assertContains('silpa_kinerja', $silpaKinerja);
        $this->assertNotContains('silpa_reguler', $silpaKinerja);
    }

    /** Migrasi mengubah ejaan lama pada data yang sudah ada. */
    public function test_migrasi_mengganti_ejaan_lama_pada_dokumen(): void
    {
        // Baris ini sudah dibuat migrasi lama dengan ejaan lama; kita hanya
        // memastikan keadaannya benar-benar ejaan lama sebelum migrasi diuji.
        DB::table('bos_types')->updateOrInsert(['kode' => 'bosda'],
            ['nama' => 'BOSP Daerah', 'updated_at' => now()]);
        DB::table('bos_types')->updateOrInsert(['kode' => 'afirmasi'],
            ['nama' => 'BOSP Afirmasi', 'updated_at' => now()]);

        $sekolah = \App\Models\School::create(['npsn' => '90000303', 'nama' => 'SD Migrasi',
            'jenjang' => 'SD', 'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);

        RkasHeader::create(['school_id' => $sekolah->id, 'tahun' => 2026, 'revisi_ke' => 1,
            'sumber_dana' => 'BOSP Daerah', 'jenis_rkas' => 'murni', 'status' => 'draft']);

        /** @var object{up:callable} $migrasi */
        $migrasi = require database_path('migrations/2026_10_28_000001_samakan_sumber_dana_arkas.php');
        $migrasi->up();

        $this->assertSame('BOS Daerah', BosType::where('kode', 'bosda')->value('nama'));
        $this->assertSame('BOS Afirmasi', BosType::where('kode', 'afirmasi')->value('nama'));
        $this->assertSame('SiLPA BOS Reguler', BosType::where('kode', 'silpa_reguler')->value('nama'));
        $this->assertSame('SiLPA BOS Afirmasi', BosType::where('kode', 'silpa_afirmasi')->value('nama'));

        $this->assertSame(
            'BOS Daerah',
            RkasHeader::withoutGlobalScope('school')->where('school_id', $sekolah->id)->value('sumber_dana')
        );

        // Idempoten: dijalankan dua kali hasilnya sama.
        $migrasi->up();
        $this->assertSame('BOS Daerah', BosType::where('kode', 'bosda')->value('nama'));
    }

    /** Ejaan lama tetap dipetakan ke tab yang sama (dokumen lama tidak pindah). */
    public function test_ejaan_lama_tetap_dikenali(): void
    {
        $this->assertSame('daerah', RkasHeader::tabDariSumber('BOSP Daerah'));
        $this->assertSame('tambahan', RkasHeader::tabDariSumber('BOSP Afirmasi'));
        $this->assertSame('silpa_kinerja', RkasHeader::tabDariSumber('SiLPA BOSP Kinerja'));
        $this->assertSame('lainnya', RkasHeader::tabDariSumber('Sumber Dana Lainnya'));
    }
}
