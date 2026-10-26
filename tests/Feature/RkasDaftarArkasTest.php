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
 * Daftar Kertas Kerja mengikuti struktur ARKAS 4:
 * tab sumber dana + satu baris per tahun anggaran (revisi dilipat).
 */
class RkasDaftarArkasTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $operator;

    private Rekening $rekening;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterSeeder::class);

        $this->school = School::create([
            'npsn' => '40301003', 'nama' => 'SDN Daftar Arkas', 'jenjang' => 'SD',
            'status_sekolah' => 'negeri', 'jumlah_siswa' => 100, 'satuan_biaya_bosp' => 1_000_000,
            'nama_komite' => 'Komite', 'email_komite' => 'k@sdn.test',
            'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->operator = User::create([
            'name' => 'Bendahara', 'email' => 'op3@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $this->school->id,
        ]);

        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        Allocation::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'tahun' => 2026,
            'bos_type_id' => $bos->id, 'nilai' => 100_000_000,
        ]);

        $this->rekening = Rekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();
    }

    private function buat(int $tahun, string $sumber, int $revisi = 1, string $status = 'draft'): RkasHeader
    {
        $h = RkasHeader::create([
            'school_id' => $this->school->id,
            'tahun' => $tahun,
            'revisi_ke' => $revisi,
            'status' => $status,
            'sumber_dana' => $sumber,
            'jenis_rkas' => 'murni',
        ]);

        $h->details()->create([
            'rekening_id' => $this->rekening->id,
            'kode_kegiatan' => '06.05',
            'uraian' => 'ATK '.$tahun.' rev '.$revisi,
            'satuan' => 'paket',
            'banyak' => 1,
            'harga_satuan' => 10_000_000,
            'jumlah' => 10_000_000,
            'sumber_dana' => $sumber,
        ]);

        return $h->fresh();
    }

    public function test_pemetaan_sumber_dana_ke_tab_arkas(): void
    {
        $this->assertSame('reguler', RkasHeader::tabDariSumber('BOS Reguler'));
        $this->assertSame('daerah', RkasHeader::tabDariSumber('BOSP Daerah'));
        $this->assertSame('kinerja', RkasHeader::tabDariSumber('BOS Kinerja'));
        $this->assertSame('silpa_kinerja', RkasHeader::tabDariSumber('SiLPA BOSP Kinerja'));
        $this->assertSame('silpa_kinerja', RkasHeader::tabDariSumber('SILPA'));
        $this->assertSame('tambahan', RkasHeader::tabDariSumber('BOSP Afirmasi'));
        $this->assertSame('lainnya', RkasHeader::tabDariSumber('Sumber Dana Lainnya'));
        // Nilai tak dikenal tetap punya rumah agar dokumen tidak hilang.
        $this->assertSame('lainnya', RkasHeader::tabDariSumber('Dana Bantuan Khusus'));
        $this->assertSame('reguler', RkasHeader::tabDariSumber(null));
    }

    public function test_enam_tab_sumber_dana_dan_tab_reguler_aktif_default(): void
    {
        $html = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get(route('rkas.index'))
            ->assertOk()
            ->getContent();

        foreach (['BOSP Reguler', 'BOSP Daerah', 'BOSP Kinerja', 'SiLPA BOSP Kinerja',
                  'Lainnya', 'Sumber Dana Tambahan'] as $label) {
            $this->assertStringContainsString($label, $html, 'Tab '.$label.' tidak ada.');
        }

        // Tab reguler harus ditandai aktif (aria-selected=true).
        $this->assertMatchesRegularExpression('/aria-selected="true"[^>]*>\s*BOSP Reguler/', $html);
    }

    public function test_tab_menyaring_dokumen_per_sumber_dana(): void
    {
        $this->buat(2026, 'BOS Reguler');
        $this->buat(2026, 'BOS Kinerja');

        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('rkas.index', ['tab' => 'reguler']))
            ->assertOk()
            ->assertSee('RKAS BOS Reguler 2026')
            ->assertDontSee('RKAS BOS Kinerja 2026');

        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('rkas.index', ['tab' => 'kinerja']))
            ->assertOk()
            ->assertSee('RKAS BOS Kinerja 2026')
            ->assertDontSee('RKAS BOS Reguler 2026');
    }

    public function test_satu_baris_per_tahun_dan_revisi_dilipat(): void
    {
        $this->buat(2026, 'BOS Reguler', 1, 'draft');
        $this->buat(2026, 'BOS Reguler', 2, 'disahkan');

        $html = $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('rkas.index'))
            ->assertOk()
            ->getContent();

        // Revisi terbaru jadi representasi baris; revisi lama tidak jadi baris sendiri.
        $this->assertSame(1, substr_count($html, '📂 Buka'), 'Revisi pada tahun yang sama harus dilipat jadi satu baris.');
        $this->assertStringContainsString('Riwayat (2)', $html, 'Tombol riwayat harus menampilkan jumlah revisi.');
        $this->assertStringContainsString('Revisi ke-1', $html, 'Revisi lama harus tetap bisa diakses lewat riwayat.');
        $this->assertStringContainsString('2 revisi', $html);
    }

    public function test_riwayat_tiga_tahun_dan_tahun_lampau_tanpa_dokumen(): void
    {
        $this->buat(2026, 'BOS Reguler');

        $html = $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('rkas.index'))
            ->assertOk()
            ->assertSee('RKAS BOS Reguler 2026')
            ->getContent();

        // ARKAS menampilkan 3 tahun sekaligus; tahun lampau tanpa dokumen diberi chip Nonaktif
        // dan tidak menawarkan Aktivasi (tenggatnya sudah lewat).
        $this->assertStringContainsString('Kertas Kerja BOSP Reguler 2025', $html);
        $this->assertStringContainsString('Nonaktif - Lewat Batas', $html);
        $this->assertStringContainsString('tidak diaktifkan', $html);
        $this->assertStringNotContainsString('Aktivasi Kertas Kerja', $html,
            'Tahun lampau tanpa dokumen tidak boleh menawarkan Aktivasi.');
    }

    public function test_tahun_aktif_tanpa_dokumen_menawarkan_aktivasi(): void
    {
        $this->buat(2025, 'BOS Reguler');   // hanya tahun lampau yang punya dokumen

        $html = $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('rkas.index'))
            ->assertOk()
            ->assertSee('Aktivasi Kertas Kerja')
            ->getContent();

        $this->assertStringContainsString('RKAS BOS Reguler 2025', $html);
        $this->assertStringContainsString('Nonaktif - Lewat Batas', $html);
    }

    public function test_tautan_lama_sumber_tetap_bekerja(): void
    {
        $this->buat(2026, 'BOS Kinerja');

        // Dulu filter memakai ?sumber=…; tautan/bookmark lama tidak boleh rusak.
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('rkas.index', ['sumber' => 'BOS Kinerja']))
            ->assertOk()
            ->assertSee('RKAS BOS Kinerja 2026');
    }

    public function test_tab_tambahan_menampilkan_afirmasi(): void
    {
        $this->buat(2026, 'BOSP Afirmasi');

        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('rkas.index', ['tab' => 'tambahan']))
            ->assertOk()
            ->assertSee('RKAS BOSP Afirmasi 2026')
            ->assertSee('Sumber Dana Tambahan');
    }
}
