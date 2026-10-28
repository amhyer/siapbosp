<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tab sumber dana ala ARKAS 4 pada halaman yang MEMANG menyaring per sumber
 * dana (Penganggaran, Pendapatan, Belanja).
 *
 * ARKAS menampilkan pita tab di setiap halaman. Tab hanya boleh dipasang bila
 * benar-benar menyaring — tab mati (tautan yang tidak mengubah apa pun) lebih
 * membingungkan daripada tidak ada tab.
 */
class TabSumberDanaTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private School $sekolah;

    private BosType $bosReguler;

    private BosType $bosKinerja;

    private Stage $tahap;

    private int $rekPendapatan;

    private int $rekBelanja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000101', 'nama' => 'SD Tab', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);

        $this->op = User::create([
            'name' => 'Op Tab', 'email' => 'tab@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);

        $this->bosReguler = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        $this->bosKinerja = BosType::firstOrCreate(['kode' => 'kinerja'], ['nama' => 'BOS Kinerja']);

        $this->rekPendapatan = Rekening::firstOrCreate(['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Provinsi', 'tipe' => 'pendapatan'])->id;
        $this->rekBelanja = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'],
            ['nama' => 'Honor Pengelola', 'tipe' => 'belanja'])->id;

        $this->tahap = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Allocation::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2026,
            'bos_type_id' => $this->bosReguler->id, 'stage_id' => null, 'nilai' => 100_000_000,
        ]);

        $this->actingAs($this->op);
    }

    private function halaman(string $url): string
    {
        return $this->withSession(['tahun' => 2026])->get($url)->assertOk()->getContent();
    }

    /** Pendapatan tersimpan dengan jenis BOS tertentu. */
    private function pendapatan(BosType $bos, string $keterangan, int $nilai = 1_000_000): void
    {
        $this->withSession(['tahun' => 2026])->post(route('pendapatan.store'), [
            'tanggal' => '2026-02-10', 'bos_type_id' => $bos->id, 'stage_id' => $this->tahap->id,
            'keterangan' => $keterangan,
            'rekening_id' => [$this->rekPendapatan], 'nilai' => [$nilai],
        ])->assertSessionHas('ok');
    }

    private function belanja(BosType $bos, string $keterangan): void
    {
        $this->withSession(['tahun' => 2026])->post(route('belanja.store'), [
            'tanggal' => '2026-03-15', 'bos_type_id' => $bos->id, 'stage_id' => $this->tahap->id,
            'metode_bayar' => 'transfer', 'keterangan' => $keterangan,
            'rekening_id' => [$this->rekBelanja], 'nilai' => [500_000],
        ])->assertSessionHas('ok');
    }

    /** Tiga halaman itu menampilkan pita tab yang sama dengan ARKAS. */
    public function test_tiga_halaman_menampilkan_pita_tab_sumber_dana(): void
    {
        foreach (['/rkas', '/penerimaan/pendapatan', '/pengeluaran/belanja'] as $url) {
            $html = $this->halaman($url);

            $this->assertStringContainsString('role="tablist"', $html, $url.' tanpa pita tab.');
            foreach (['BOSP Reguler', 'BOSP Daerah', 'BOSP Kinerja', 'SiLPA BOSP Kinerja', 'Lainnya', 'Sumber Dana Tambahan'] as $label) {
                $this->assertStringContainsString($label, $html, $url.' kehilangan tab '.$label.'.');
            }

            // Enam tab ARKAS (+1 "Semua sumber dana" di halaman transaksi).
            $jumlahTab = substr_count($html, 'role="tab"');
            $this->assertGreaterThanOrEqual(6, $jumlahTab, $url.' hanya punya '.$jumlahTab.' tab.');
            $this->assertStringContainsString('aria-selected="true"', $html, $url.' tanpa tab aktif.');
        }
    }

    /** Tab di halaman transaksi benar-benar menyaring (bukan tab mati). */
    public function test_tab_pendapatan_menyaring_per_jenis_bos(): void
    {
        $this->pendapatan($this->bosReguler, 'Pencairan reguler');
        $this->pendapatan($this->bosKinerja, 'Pencairan kinerja');

        $semua = $this->halaman('/penerimaan/pendapatan?tab=');
        $this->assertStringContainsString('Pencairan reguler', $semua);
        $this->assertStringContainsString('Pencairan kinerja', $semua);

        $reguler = $this->halaman('/penerimaan/pendapatan?tab=reguler');
        $this->assertStringContainsString('Pencairan reguler', $reguler);
        $this->assertStringNotContainsString('Pencairan kinerja', $reguler);

        $kinerja = $this->halaman('/penerimaan/pendapatan?tab=kinerja');
        $this->assertStringContainsString('Pencairan kinerja', $kinerja);
        $this->assertStringNotContainsString('Pencairan reguler', $kinerja);

        // Tab aktif ditandai untuk pembaca layar & warna garis bawah.
        $this->assertStringContainsString('aria-selected="true"', $kinerja);
        $this->assertStringContainsString('border-indigo-600', $kinerja);
    }

    /** Tab belanja juga menyaring, dan menyebut "Semua sumber dana". */
    public function test_tab_belanja_menyaring_dan_ada_pilihan_semua(): void
    {
        $this->pendapatan($this->bosReguler, 'Dana masuk');
        $this->belanja($this->bosReguler, 'Belanja reguler');
        $this->belanja($this->bosKinerja, 'Belanja kinerja');

        $html = $this->halaman('/pengeluaran/belanja');
        $this->assertStringContainsString('Semua sumber dana', $html);

        $kinerja = $this->halaman('/pengeluaran/belanja?tab=kinerja');
        $this->assertStringContainsString('Belanja kinerja', $kinerja);
        $this->assertStringNotContainsString('Belanja reguler', $kinerja);
    }

    /** Tab tak dikenal tidak menyaring apa pun (dan tidak error). */
    public function test_tab_tak_dikenal_kembali_ke_semua(): void
    {
        $this->pendapatan($this->bosReguler, 'Pencairan reguler');
        $this->pendapatan($this->bosKinerja, 'Pencairan kinerja');

        $html = $this->halaman('/penerimaan/pendapatan?tab=ngawur');
        $this->assertStringContainsString('Pencairan reguler', $html);
        $this->assertStringContainsString('Pencairan kinerja', $html);
    }

    /** Pencarian tetap terbawa saat berpindah tab (query dipertahankan). */
    public function test_pencarian_terbawa_saat_berpindah_tab(): void
    {
        $html = $this->halaman('/penerimaan/pendapatan?q=pencairan');

        // Urutan parameter tidak dijanjikan; yang penting keduanya ada di tautan yang sama.
        $this->assertMatchesRegularExpression(
            '/href="[^"]*(?=[^"]*tab=reguler)(?=[^"]*q=pencairan)[^"]*"/', $html,
            'Tautan tab harus mempertahankan kata kunci pencarian.');
    }

    /** Pemetaan tab -> jenis BOS tersedia dan tidak kosong untuk 6 tab. */
    public function test_pemetaan_tab_ke_jenis_bos(): void
    {
        // Tab Reguler memuat BOS Reguler DAN SiLPA BOS Reguler (ARKAS tidak
        // memisahkan tab SiLPA Reguler), jadi pencocokan memakai assertContains.
        $this->assertContains($this->bosReguler->id, RkasHeader::bosTypeIds('reguler'));
        $this->assertContains($this->bosKinerja->id, RkasHeader::bosTypeIds('kinerja'));
        $this->assertSame([], RkasHeader::bosTypeIds('tidak-ada'));
    }
}
