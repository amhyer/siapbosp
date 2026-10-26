<?php

namespace Tests\Feature;

use App\Models\BkuTutupBulan;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Arsip: filter Format · Sumber dana · Tahun pada SEMUA halaman arsip.
 *
 * Sebelumnya hanya `/arsip` yang punya filter lengkap. `arsip/bku` tanpa
 * filter apa pun, sedangkan `arsip/rkas` dan `arsip/pajak` memakai rentang
 * tahun yang dipaku 2023–2028 — sehingga sekolah dengan arsip 2021/2022
 * melihat select tanpa nilai terpilih.
 */
class ArsipFilterTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000111', 'nama' => 'SD Arsip', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);

        $this->op = User::create([
            'name' => 'Op Arsip', 'email' => 'arsip@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);

        $this->actingAs($this->op);
    }

    private function halaman(string $url, int $tahun = 2026): string
    {
        return $this->withSession(['tahun' => $tahun])->get($url)->assertOk()->getContent();
    }

    private function rkasDisahkan(string $sumber, int $tahun): RkasHeader
    {
        return RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => $tahun, 'revisi_ke' => 1,
            'status' => 'disahkan', 'sumber_dana' => $sumber, 'jenis_rkas' => 'murni',
        ]);
    }

    /** Tahun di luar 2023–2028 tetap muncul & terpilih (dulu mustahil). */
    public function test_tahun_arsip_lama_tetap_terpilih(): void
    {
        foreach (['/arsip/rkas', '/arsip/pajak', '/arsip/bku'] as $url) {
            $html = $this->halaman($url.'?tahun=2021', 2021);

            $this->assertStringContainsString('name="tahun"', $html, $url.' tanpa filter tahun.');
            $this->assertMatchesRegularExpression('/<option value="2021"\s+selected/', $html,
                $url.' tidak menandai tahun 2021 sebagai terpilih.');
        }
    }

    /** Tahun sesi selalu tersedia walau belum ada dokumen sama sekali. */
    public function test_tahun_sesi_tersedia_tanpa_dokumen(): void
    {
        $html = $this->halaman('/arsip/rkas', 2027);

        $this->assertMatchesRegularExpression('/<option value="2027"\s+selected/', $html);
    }

    /** BKU kini punya filter tahun (sebelumnya tidak ada filter apa pun). */
    public function test_arsip_bku_punya_filter_tahun_yang_bekerja(): void
    {
        BkuTutupBulan::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2025, 'bulan' => '2025-03',
            'ditutup_oleh' => 'Operator', 'ditutup_at' => now(),
        ]);

        $html2025 = $this->halaman('/arsip/bku?tahun=2025', 2026);
        $this->assertStringContainsString('id="arsip-tahun"', $html2025);
        $this->assertStringContainsString('Maret', $html2025);   // baris bulan 2025-03 tampil

        $html2026 = $this->halaman('/arsip/bku?tahun=2026', 2026);
        $this->assertStringNotContainsString('Maret', $html2026); // tahun lain tidak tercampur
    }

    /** Filter sumber dana di arsip RKAS benar-benar menyaring. */
    public function test_filter_sumber_dana_arsip_rkas_menyaring(): void
    {
        $this->rkasDisahkan('BOS Reguler', 2026);
        $this->rkasDisahkan('BOS Kinerja', 2026);

        $semua = $this->halaman('/arsip/rkas?tahun=2026');
        $this->assertStringContainsString('BOS Reguler', $semua);
        $this->assertStringContainsString('BOS Kinerja', $semua);
        $this->assertStringContainsString('name="sumber"', $semua);

        $kinerja = $this->halaman('/arsip/rkas?tahun=2026&sumber=BOS+Kinerja');
        $this->assertStringContainsString('BOS Kinerja', $kinerja);
        $this->assertStringNotContainsString('>BOS Reguler<', $kinerja);
    }

    /** Bentuk filter sama di semua halaman arsip (partial bersama). */
    public function test_bentuk_filter_seragam_di_semua_halaman_arsip(): void
    {
        foreach (['/arsip', '/arsip/rkas', '/arsip/bku', '/arsip/pajak'] as $url) {
            $html = $this->halaman($url);

            $this->assertStringContainsString('data-kirim-otomatis', $html, $url.' tidak memakai kirim-otomatis.');
            $this->assertStringContainsString('Tahun', $html, $url.' tanpa label Tahun.');
        }
    }
}
