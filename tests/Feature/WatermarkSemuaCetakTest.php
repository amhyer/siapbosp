<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Support\StatusDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Watermark DRAFT di SEMUA cetakan (ARKAS 4 Bab XXI).
 *
 * Sebelumnya tanda air hanya dipasang oleh view cetak BKU, sehingga Buku
 * Pembantu, Rekap Realisasi, SPTJM, dan SPJ keluar tanpa penanda apa pun
 * padahal datanya memuat bulan yang BKU-nya belum ditutup.
 *
 * Aturannya: DRAFT muncul selama ada bulan yang PUNYA TRANSAKSI tetapi
 * belum ditutup. Sekolah yang belum punya transaksi tidak dicap DRAFT.
 */
class WatermarkSemuaCetakTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private User $kepsek;

    private int $bosId;

    private int $rekPendapatan;

    private Stage $tahap;

    protected function setUp(): void
    {
        parent::setUp();

        $sekolah = School::create([
            'npsn' => '90000091', 'nama' => 'SD Watermark', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);

        $this->op = User::create([
            'name' => 'Bendahara WM', 'email' => 'wm@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $sekolah->id,
        ]);
        $this->kepsek = User::create([
            'name' => 'Kepsek WM', 'email' => 'kep-wm@t.t', 'password' => 'p',
            'role' => 'kepsek', 'school_id' => $sekolah->id,
        ]);

        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;
        $this->rekPendapatan = Rekening::firstOrCreate(['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Provinsi', 'tipe' => 'pendapatan'])->id;

        $this->tahap = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Allocation::create([
            'school_id' => $sekolah->id, 'tahun' => 2026, 'bos_type_id' => $this->bosId,
            'stage_id' => null, 'nilai' => 50_000_000,
        ]);

        $this->actingAs($this->op);
    }

    private function catatFebruari(): void
    {
        $this->withSession(['tahun' => 2026])->post(route('pendapatan.store'), [
            'tanggal' => '2026-02-10', 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap->id,
            'keterangan' => 'Pencairan tahap 1',
            'rekening_id' => [$this->rekPendapatan], 'nilai' => [5_000_000],
        ])->assertSessionHas('ok');
    }

    private function halaman(string $url): string
    {
        return $this->withSession(['tahun' => 2026])->get($url)->assertOk()->getContent();
    }

    /** Semua cetakan yang memuat data bulan terbuka bertanda DRAFT. */
    public function test_semua_cetakan_bertanda_draft_saat_bulan_belum_ditutup(): void
    {
        $this->catatFebruari();

        $halaman = [
            'Rekap Realisasi (layouts.print)' => '/pembukuan/rekap-realisasi?semester=1',
            'Buku Pembantu (kerangka aplikasi)' => '/pembukuan/buku-pembantu?semester=1',
            'SPTJM (kerangka aplikasi)' => '/pembukuan/sptjm?semester=1',
        ];

        foreach ($halaman as $nama => $url) {
            $html = $this->halaman($url);

            $this->assertStringContainsString('Watermark DRAFT aktif', $html, $nama.' tidak bertanda air DRAFT.');
            $this->assertStringContainsString('>DRAFT<', $html, $nama.' tidak memuat tanda air DRAFT.');
            $this->assertStringContainsString('belum ditutup', $html, $nama.' tidak menjelaskan sebab DRAFT.');
        }
    }

    /** Setelah bulannya ditutup, tanda air hilang dari semua cetakan itu. */
    public function test_tanda_air_hilang_setelah_bulan_ditutup(): void
    {
        $this->catatFebruari();
        $this->withSession(['tahun' => 2026])->post(route('bku.tutup'), ['bulan' => '2026-02'])
            ->assertSessionHas('ok');

        foreach (['/pembukuan/rekap-realisasi?semester=1',
            '/pembukuan/buku-pembantu?semester=1',
            '/pembukuan/sptjm?semester=1'] as $url) {
            $this->assertStringNotContainsString('Watermark DRAFT aktif', $this->halaman($url), $url.' masih DRAFT.');
        }
    }

    /** Sekolah tanpa transaksi tidak dicap DRAFT (dokumen kosong bukan "belum final"). */
    public function test_tanpa_transaksi_tidak_bertanda_draft(): void
    {
        $html = $this->halaman('/pembukuan/buku-pembantu?semester=1');

        $this->assertStringNotContainsString('Watermark DRAFT aktif', $html);
    }

    /** Perhitungan intinya: bulan bertransaksi dikurangi bulan yang sudah ditutup. */
    public function test_status_draft_menghitung_bulan_bertransaksi_yang_belum_ditutup(): void
    {
        $this->assertSame([], StatusDraft::bulanBelumTutup(2026));
        $this->assertFalse(StatusDraft::ada(2026));

        $this->catatFebruari();
        $this->assertSame(['2026-02'], StatusDraft::bulanBelumTutup(2026));
        $this->assertTrue(StatusDraft::ada(2026));

        $this->withSession(['tahun' => 2026])->post(route('bku.tutup'), ['bulan' => '2026-02']);
        $this->assertSame([], StatusDraft::bulanBelumTutup(2026));
        $this->assertFalse(StatusDraft::ada(2026));
    }

    /**
     * Cetak BKU tetap memakai rentang Format × Periode dari controller
     * (bukan perhitungan global) — dan tidak muncul dua kali.
     */
    public function test_cetak_bku_tetap_memakai_rentang_format_dan_tidak_ganda(): void
    {
        $this->catatFebruari();

        // Format Tahunan = 12 bulan; satu bulan terbuka → DRAFT, sekali saja.
        $html = $this->halaman('/pembukuan/bku/cetak?format=tahunan&bulan=2026-02');
        $this->assertSame(1, substr_count($html, '>DRAFT<'), 'Tanda air DRAFT muncul lebih dari sekali.');
        $this->assertStringContainsString('Watermark DRAFT aktif', $html);
    }
}
