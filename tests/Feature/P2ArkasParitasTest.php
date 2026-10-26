<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\User;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2 — paritas kecil ARKAS 4:
 *   1) badge versi di topbar (padanan "Perbarui Aplikasi"),
 *   2) tombol tahun anggaran di topbar,
 *   3) footer cetak "bukan dokumen resmi ARKAS",
 *   4) kolom Panduan per baris rincian Kertas Kerja.
 *
 * Rujukan: docs/MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md §P2.
 */
class P2ArkasParitasTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $op;

    private RkasHeader $rkas;

    private const KODE_REKENING = '5.1.02.01.01.0024';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterSeeder::class);

        $this->school = School::create([
            'npsn' => '40309903', 'nama' => 'SDN Paritas P2', 'jenjang' => 'SD',
            'status_sekolah' => 'negeri', 'jumlah_siswa' => 100, 'satuan_biaya_bosp' => 1_000_000,
            'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->op = User::create([
            'name' => 'Bendahara P2', 'email' => 'p2@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $this->school->id,
        ]);

        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        Allocation::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'tahun' => 2026, 'bos_type_id' => $bos->id, 'nilai' => 100_000_000,
        ]);

        $this->rkas = RkasHeader::create([
            'school_id' => $this->school->id, 'tahun' => 2026, 'revisi_ke' => 1, 'status' => 'draft',
            'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni',
        ]);

        $rek = Rekening::where('kode', self::KODE_REKENING)->firstOrFail();
        $this->rkas->details()->create([
            'rekening_id' => $rek->id, 'kode_kegiatan' => '06.05', 'uraian' => 'ATK Operasional',
            'satuan' => 'paket', 'banyak' => 1, 'harga_satuan' => 5_000_000, 'jumlah' => 5_000_000,
            'sumber_dana' => 'BOS Reguler',
        ]);
        $this->rkas = $this->rkas->fresh();

        $this->actingAs($this->op);
    }

    private function halaman(string $url, int $tahun = 2026): string
    {
        return $this->withSession(['tahun' => $tahun])->get($url)->assertOk()->getContent();
    }

    public function test_badge_versi_tampil_di_topbar_dan_menuju_bagian_versi(): void
    {
        $html = $this->halaman(route('dashboard'));

        // Badge memakai config('app.version') — bukan angka yang ditulis ulang di view.
        $this->assertStringContainsString('⟳ '.config('app.version'), $html);
        $this->assertStringContainsString(route('bantuan').'#versi', $html);

        // Target anchor-nya benar-benar ada.
        $bantuan = $this->halaman(route('bantuan'));
        $this->assertStringContainsString('id="versi"', $bantuan);
    }

    public function test_tahun_anggaran_di_topbar_selalu_memuat_tahun_aktif(): void
    {
        // Tahun di luar rentang bawaan 2023–2028 tetap harus muncul & terpilih,
        // kalau tidak operator melihat tahun yang salah di topbar.
        $html = $this->halaman(route('dashboard'), 2031);

        $this->assertStringContainsString('name="tahun"', $html);
        $this->assertMatchesRegularExpression('/<option value="2031"\s+selected/', $html);
    }

    public function test_footer_cetak_menyatakan_bukan_dokumen_resmi_arkas(): void
    {
        $html = $this->halaman(route('bku.cetak', ['bulan' => '2026-02']));

        $this->assertStringContainsString('cetak-footer', $html);
        $this->assertStringContainsString('bukan dokumen resmi ARKAS', $html);
        $this->assertStringContainsString('Dicetak dari <b>SiapBOSP '.config('app.version').'</b>', $html);

        // Footer harus ikut tercetak: bukan elemen bertanda nonprint.
        $this->assertDoesNotMatchRegularExpression('/<footer[^>]*nonprint/', $html);
    }

    public function test_kolom_panduan_ada_di_setiap_baris_rincian(): void
    {
        $html = $this->halaman(route('rkas.show', $this->rkas));

        $this->assertStringContainsString('<th>Panduan</th>', $html);

        // Satu tombol panduan per baris rincian.
        $this->assertSame($this->rkas->details->count(), substr_count($html, 'title="Panduan &amp; acuan baris ini"'));

        // Tautan acuan memakai kode rekening baris tersebut.
        $this->assertStringContainsString(route('referensi.harga', ['q' => self::KODE_REKENING]), $html);
        $this->assertStringContainsString(route('bantuan').'#rincian', $html);
        $this->assertStringContainsString(route('penganggaran.review'), $html);
    }

    public function test_halaman_bantuan_memuat_aturan_rincian_yang_dituju_panduan(): void
    {
        $html = $this->halaman(route('bantuan'));

        $this->assertStringContainsString('id="rincian"', $html);
        $this->assertStringContainsString('Uraian maksimal 500 karakter', $html);
        $this->assertStringContainsString('Belum Dianggarkan harus Rp 0', $html);
    }
}
