<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\School;
use App\Models\User;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wizard belanja 3 langkah ala ARKAS 4 (P1-7):
 *   1) Detail Transaksi -> 2) Kegiatan & Rincian -> 3) Perhitungan Pajak.
 *
 * Yang dikunci di sini hanyalah kontrak tampilan: urutan langkah, satu form
 * tunggal ke belanja.store, tidak ada tombol navigasi yang bertipe submit,
 * dan tidak ada field lama yang hilang (nama field tetap sama supaya
 * PengeluaranController::belanjaStore tidak perlu diubah).
 */
class WizardBelanjaTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterSeeder::class);

        $school = School::create([
            'npsn' => '40309901',
            'nama' => 'SDN Wizard Belanja',
            'jenjang' => 'SD',
            'status_sekolah' => 'negeri',
            'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000,
            'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->op = User::create([
            'name' => 'Bendahara',
            'email' => 'wizard@sdn.test',
            'password' => 'secret123',
            'role' => 'operator',
            'school_id' => $school->id,
        ]);

        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        Allocation::withoutGlobalScope('school')->create([
            'school_id' => $school->id,
            'tahun' => 2026,
            'bos_type_id' => $bos->id,
            'nilai' => 100_000_000,
        ]);
    }

    private function halaman(): string
    {
        return $this->actingAs($this->op)
            ->withSession(['tahun' => 2026])
            ->get(route('belanja.create'))
            ->assertOk()
            ->getContent();
    }

    /** Ambil isi atribut class pada <div role="tabpanel" id="langkah-N"> ('' bila tidak ada). */
    private function kelasPanel(string $html, int $n): string
    {
        if (! preg_match('/<div\b[^>]*\bid="langkah-'.$n.'"[^>]*>/', $html, $m)) {
            $this->fail("Panel langkah {$n} tidak ditemukan.");
        }

        return preg_match('/\bclass="([^"]*)"/', $m[0], $c) ? $c[1] : '';
    }

    public function test_form_belanja_dibagi_tiga_langkah_berurutan_ala_arkas(): void
    {
        $html = $this->halaman();

        // Tepat satu form, tetap mengarah ke belanja.store dengan enctype berkas.
        $this->assertSame(1, preg_match_all('/<form\b[^>]*action="'.preg_quote(route('belanja.store'), '/').'"/', $html));
        $this->assertSame(1, substr_count($html, 'enctype="multipart/form-data"'));

        // Tiga panel langkah + tiga tombol stepper.
        foreach ([1, 2, 3] as $n) {
            $this->assertStringContainsString('data-langkah="'.$n.'"', $html);
            $this->assertStringContainsString('data-langkah-tombol="'.$n.'"', $html);
        }
        $this->assertSame(3, substr_count($html, 'role="tabpanel"'));

        // Panel 2 dan 3 tersembunyi di awal, panel 1 tampil.
        $this->assertStringNotContainsString('hidden', $this->kelasPanel($html, 1));
        $this->assertStringContainsString('hidden', $this->kelasPanel($html, 2));
        $this->assertStringContainsString('hidden', $this->kelasPanel($html, 3));

        // Tanpa JavaScript, wizard harus runtuh jadi satu halaman panjang seperti semula.
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString('js-langkah', $html);
        $this->assertStringContainsString('js-simpan', $html);

        // Urutan ARKAS 4: Detail Transaksi -> Kegiatan & Rincian -> Pajak.
        $detail = strpos($html, 'Detail Transaksi');
        $kegiatan = strpos($html, 'Kegiatan &amp; Rincian');
        $pajak = strpos($html, 'Perhitungan Pajak');
        $this->assertNotFalse($detail);
        $this->assertNotFalse($kegiatan);
        $this->assertNotFalse($pajak);
        $this->assertTrue($detail < $kegiatan && $kegiatan < $pajak, 'Urutan langkah harus Detail -> Kegiatan -> Pajak.');
    }

    public function test_tombol_navigasi_tidak_pernah_submit_form(): void
    {
        $html = $this->halaman();

        foreach (['data-langkah-maju', 'data-langkah-mundur'] as $atribut) {
            $this->assertMatchesRegularExpression(
                '/<button\s+type="button"[^>]*'.$atribut.'|<button\s+'.$atribut.'[^>]*type="button"/',
                $html,
                "Tombol {$atribut} harus type=button supaya tidak mengirim form."
            );
        }

        // Tombol simpan baru tampil di langkah 3 (disembunyikan lewat atribut hidden).
        $this->assertMatchesRegularExpression('/<button\b[^>]*data-wizard-simpan[^>]*\bhidden\b/', $html);

        // Navigasi antar langkah tidak boleh memakai atribut inline on* (aturan CSP R-03).
        $this->assertDoesNotMatchRegularExpression('/<button\b[^>]*\bon(click|submit)=/i', $html);
    }

    public function test_semua_field_lama_tetap_ada_dan_tidak_berubah_nama(): void
    {
        $html = $this->halaman();

        // Identitas transaksi (langkah 1).
        foreach ([
            'name="tanggal"', 'name="keterangan"', 'name="sumber"', 'name="bos_type_id"',
            'name="stage_id"', 'name="metode_bayar"', 'name="no_bukti"', 'name="no_nota"',
            'name="nama_toko"', 'name="alamat_toko"', 'name="npwp"', 'name="ongkir_siplah"',
            'name="biaya_admin_siplah"', 'name="tanpa_npwp"', 'name="usaha_perseorangan"',
        ] as $field) {
            $this->assertStringContainsString($field, $html, "Field {$field} hilang dari wizard.");
        }

        // Rincian rekening (langkah 2) dan pajak manual (langkah 3).
        $this->assertStringContainsString('name="rekening_id[]"', $html);
        $this->assertStringContainsString('name="nilai[]"', $html);
        $this->assertStringContainsString('name="nilai_pajak[]"', $html);
        $this->assertStringContainsString('name="pajak_id[]"', $html);
        $this->assertStringContainsString('name="tarif_pajak[]"', $html);
        $this->assertStringContainsString('name="uraian[]"', $html);

        // Lampiran bukti ikut langkah 1.
        $this->assertStringContainsString('name="lampiran[]"', $html);

        // Template baris & tombol aksi lama masih dipakai JS.
        foreach (['id="tplR"', 'id="tplP"', 'data-tambah-rincian', 'data-tambah-pajak', 'data-rekomendasi-pajak'] as $penanda) {
            $this->assertStringContainsString($penanda, $html);
        }
    }

    public function test_langkah_satu_memuat_identitas_dan_lampiran_saja(): void
    {
        $html = $this->halaman();

        $awal = strpos($html, 'id="langkah-1"');
        $akhir = strpos($html, 'id="langkah-2"');
        $this->assertNotFalse($awal);
        $this->assertNotFalse($akhir);

        $langkah1 = substr($html, $awal, $akhir - $awal);
        $this->assertStringContainsString('name="tanggal"', $langkah1);
        $this->assertStringContainsString('name="lampiran[]"', $langkah1);
        $this->assertStringNotContainsString('name="nilai[]"', $langkah1);
        $this->assertStringNotContainsString('name="nilai_pajak[]"', $langkah1);

        $awal2 = $akhir;
        $akhir2 = strpos($html, 'id="langkah-3"');
        $langkah2 = substr($html, $awal2, $akhir2 - $awal2);

        // Baris rincian dibuat JS dari template, jadi yang ada di markup statis
        // langkah 2 adalah tabel kosong + tombol tambah; nilai[] ada di #tplR.
        $this->assertStringContainsString('id="tblR"', $langkah2);
        $this->assertStringContainsString('data-tambah-rincian', $langkah2);
        $this->assertStringNotContainsString('name="nilai_pajak[]"', $langkah2);

        $this->assertMatchesRegularExpression('/<template\s+id="tplR"[\s\S]*?name="nilai\[\]"[\s\S]*?<\/template>/', $html);
    }

    public function test_tarif_ppn_dan_ringkasan_tetap_tersedia(): void
    {
        $html = $this->halaman();

        // Ringkasan hanya informatif; nilainya diisi JS dari nilai baris.
        $this->assertStringContainsString('id="ringkasBelanja"', $html);
        $this->assertStringContainsString('id="ringkasPajak"', $html);
        $this->assertStringContainsString('id="wizardPosisi"', $html);

        // Peringatan tarif PPN tahun transaksi tidak boleh hilang saat refactor.
        $this->assertStringContainsString('12%', $html);
        $this->assertStringContainsString('11%', $html);
    }
}
