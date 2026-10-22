<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BosType;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu & halaman baru (hasil audit struktur menu):
 * - grup sidebar: Beranda, Data Sekolah, Penganggaran, Penatausahaan, Laporan,
 *   Arsip, Data Referensi, Pengaturan;
 * - Arsip (RKAS disahkan, BKU ditutup, dokumen pajak, riwayat impor/ekspor);
 * - Data Sekolah (tahun anggaran, sumber dana);
 * - Pengaturan (koneksi ARKAS, sinkronisasi, backup, deteksi versi, log sistem);
 * - Laporan Realisasi per Sumber Dana; Referensi dengan tab master baru.
 */
class MenuDanArsipTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private School $sekolah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000055', 'nama' => 'SD Menu', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->op = User::create([
            'name' => 'Op Menu', 'email' => 'op-menu@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        $this->actingAs($this->op);
    }

    public function test_sidebar_memuat_delapan_grup_rancangan(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        foreach (['Beranda', 'Data Sekolah', 'Penganggaran', 'Penatausahaan', 'Laporan', 'Arsip', 'Data Referensi', 'Pengaturan'] as $grup) {
            $this->assertStringContainsString($grup, $html, 'grup menu '.$grup.' tak ada');
        }
    }

    public function test_halaman_arsip_semua_terbuka(): void
    {
        foreach ([
            '/arsip/rkas' => 'RKAS Disahkan',
            '/arsip/bku' => 'Penutupan BKU Bulanan',
            '/arsip/pajak' => 'Dokumen Pajak',
            '/arsip/impor' => 'Riwayat Impor Data ARKAS',
            '/arsip/ekspor' => 'Riwayat Ekspor',
        ] as $url => $teks) {
            $this->withSession(['tahun' => 2025])->get($url)->assertOk()->assertSee($teks);
        }
    }

    public function test_arsip_menampilkan_rkas_disyahkan_dan_riwayat_impor(): void
    {
        ActivityLog::create([
            'school_id' => $this->sekolah->id, 'user_id' => $this->op->id,
            'user_name' => 'Op Menu', 'aksi' => 'impor_arkas', 'keterangan' => 'Impor uji 1 transaksi',
        ]);

        $this->withSession(['tahun' => 2025])->get('/arsip/impor')->assertOk()->assertSee('Impor uji 1 transaksi');
    }

    public function test_halaman_data_sekolah_terbuka(): void
    {
        $this->withSession(['tahun' => 2025])->get('/data-sekolah/tahun-anggaran')->assertOk()->assertSee('Tahun Anggaran');
        $this->withSession(['tahun' => 2025])->get('/data-sekolah/sumber-dana')->assertOk()->assertSee('Sumber Dana');
    }

    public function test_halaman_pengaturan_terbuka(): void
    {
        $this->withSession(['tahun' => 2025])->get('/pengaturan/koneksi-arkas')->assertOk()->assertSee('Koneksi ARKAS');
        $this->withSession(['tahun' => 2025])->get('/pengaturan/sinkronisasi')->assertOk()->assertSee('Status Sinkronisasi');
        $this->withSession(['tahun' => 2025])->get('/pengaturan/deteksi-versi')->assertOk()->assertSee('Deteksi Versi ARKAS');
        $this->withSession(['tahun' => 2025])->get('/pengaturan/log')->assertOk()->assertSee('Log Sistem');
    }

    public function test_backup_dapat_diunduh(): void
    {
        $this->withSession(['tahun' => 2025])->get('/pengaturan/backup')->assertOk()->assertSee('Backup Data Sekolah');

        $res = $this->withSession(['tahun' => 2025])->get('/pengaturan/backup/unduh')->assertOk();
        $this->assertStringContainsString('zip', (string) $res->headers->get('Content-Type'));
    }

    public function test_laporan_realisasi_per_sumber_dana_terbuka(): void
    {
        $this->withSession(['tahun' => 2025])
            ->get('/laporan-arkas/realisasi-sumber-dana')
            ->assertOk()
            ->assertSee('Realisasi per Sumber Dana');
    }

    public function test_referensi_memiliki_tab_baru(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/referensi')->assertOk()->getContent();
        foreach (['Program BOSP', 'Satuan', 'Uraian', 'PTK'] as $tab) {
            $this->assertStringContainsString($tab, $html, 'tab '.$tab.' tak ada');
        }
        $this->withSession(['tahun' => 2025])->get('/referensi?tab=ptk')->assertOk()->assertSee('Data PTK');
    }

    public function test_daftar_kegiatan_terbuka(): void
    {
        $this->withSession(['tahun' => 2025])
            ->get('/penganggaran/kegiatan')
            ->assertOk()
            ->assertSee('Daftar Kegiatan');
    }

    public function test_halaman_arsip_tak_bisa_diakses_dinas(): void
    {
        $dinas = User::create([
            'name' => 'Dinas', 'email' => 'dinas-menu@t.t', 'password' => 'p',
            'role' => 'dinas', 'school_id' => null,
        ]);

        $this->actingAs($dinas)->get('/arsip/rkas')->assertForbidden();
    }
}
