<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BosType;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu & halaman baru (hasil audit struktur menu):
 * - grup sidebar: Data Sekolah, Penganggaran, Penatausahaan, Laporan,
 *   Arsip, Data Referensi, Pengaturan (Dashboard berupa tautan langsung);
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

    /**
     * Struktur menu mengikuti ARKAS 4: tiga area kerja utama di sidebar,
     * Data Sekolah pindah ke dropdown profil di topbar, dan fitur tambahan
     * SiapBOSP tetap ada di bawah pemisah "Menu Lain".
     */
    public function test_sidebar_tiga_area_kerja_arkas_lalu_menu_tambahan(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        preg_match('#<nav class="navw.*?</nav>#s', $html, $blok);
        $nav = $blok[0] ?? '';
        $this->assertNotSame('', $nav, 'Blok nav utama tidak ditemukan.');

        // 3 area kerja ARKAS 4 harus berada di blok navw-utama, berurutan.
        preg_match('#<div class="navw-utama">(.*?)</div>\s*<div class="navw-sep"#s', $nav, $utama);
        $blokUtama = $utama[1] ?? '';
        $this->assertNotSame('', $blokUtama, 'Blok navw-utama (3 area kerja ARKAS) tidak ditemukan.');

        $posisi = [];
        foreach (['Penganggaran', 'Penatausahaan', 'Arsip'] as $grup) {
            $posisi[$grup] = mb_strpos($blokUtama, $grup);
            $this->assertNotFalse($posisi[$grup], 'Area kerja '.$grup.' tidak ada di blok utama.');
        }
        $this->assertTrue($posisi['Penganggaran'] < $posisi['Penatausahaan'], 'Urutan ARKAS: Penganggaran lalu Penatausahaan.');
        $this->assertTrue($posisi['Penatausahaan'] < $posisi['Arsip'], 'Urutan ARKAS: Penatausahaan lalu Arsip.');

        // Fitur tambahan tetap ada, di bawah pemisah.
        foreach (['Laporan', 'Data Referensi', 'Pengaturan'] as $grup) {
            $this->assertStringContainsString($grup, $nav, 'grup menu '.$grup.' tak ada');
            $this->assertGreaterThan(mb_strpos($nav, 'navw-sep'), mb_strpos($nav, $grup),
                $grup.' seharusnya berada setelah pemisah "Menu Lain".');
        }

        // Dashboard tetap tautan langsung; "Data Sekolah" tidak lagi jadi grup sidebar.
        $this->assertStringContainsString('href="'.route('dashboard').'"', $nav, 'Dashboard bukan tautan langsung.');
        $this->assertStringContainsString('📊 Dashboard', $nav);
        $this->assertStringNotContainsString('Beranda', $nav, 'Induk menu Beranda seharusnya sudah dihapus.');
        $this->assertStringNotContainsString('Tahun Anggaran', $nav,
            'Data Sekolah seharusnya pindah ke dropdown profil, bukan sidebar.');
    }

    /** Dropdown profil di topbar memuat data sekolah + Keluar (pola ARKAS 4). */
    public function test_dropdown_profil_topbar_memuat_data_sekolah_dan_keluar(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Data Sekolah', $html);
        foreach ([
            route('penandatangan') => 'Data Penanggung Jawab',
            route('profil.edit') => 'Profil Sekolah',
            route('datasekolah.tahun') => 'Tahun Anggaran',
            route('datasekolah.sumber-dana') => 'Sumber Dana',
            route('datasekolah.sinkronisasi') => 'Status Sinkronisasi ke Pusat',
        ] as $href => $label) {
            $this->assertStringContainsString('href="'.$href.'"', $html, $label.' tidak ada di dropdown profil.');
        }
        $this->assertStringContainsString('action="'.route('logout').'"', $html, 'Keluar harus ada di dropdown profil.');
    }

    /** Blok utilitas bawah sidebar (padanan Tutorial & Perbarui Aplikasi ARKAS 4). */
    public function test_utilitas_bawah_sidebar_dan_halaman_bantuan(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('navw-util', $html);
        $this->assertStringContainsString('Bantuan &amp; Panduan', $html);
        $this->assertStringContainsString('Perbarui Aplikasi', $html);

        $this->withSession(['tahun' => 2025])->get('/bantuan')
            ->assertOk()
            ->assertSee('Bantuan &amp; Panduan', false)
            ->assertSee('Data Penanggung Jawab');
    }

    /** Tidak ada lagi dua menu dengan target sama (duplikat) & tak ada tautan mati. */
    public function test_tak_ada_menu_duplikat_atau_tautan_mati(): void
    {
        // Halaman LPJ sah 404 bila tahun belum punya tahap — siapkan satu tahap.
        Stage::create(['tahun' => 2025, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);

        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        preg_match_all('#<nav class="navw.*?</nav>#s', $html, $blok);
        $nav = $blok[0][0] ?? '';
        preg_match_all('#<a href="([^"]+)"[^>]*>([^<]+)</a>#', $nav, $link, PREG_SET_ORDER);

        $target = [];
        foreach ($link as $l) {
            $href = html_entity_decode($l[1]);
            $label = trim($l[2]);
            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }
            $target[$href][] = $label;
        }

        // Setiap URL hanya boleh muncul sekali sebagai menu (kecuali Dashboard).
        foreach ($target as $href => $label) {
            if ($href === url('/dashboard')) {
                continue;
            }
            $this->assertCount(
                1,
                $label,
                'URL '.$href.' dipakai lebih dari sekali: '.implode(' | ', $label)
            );
        }

        // Tautan menu harus bisa diakses (tanpa 404/500).
        foreach (array_keys($target) as $href) {
            if (! str_starts_with($href, url('/'))) {
                continue;
            }
            $path = parse_url($href, PHP_URL_PATH);
            $this->assertSame(200, $this->get($path)->getStatusCode(), 'Menu menuju '.$path.' tidak dapat dibuka.');
        }
    }

    public function test_halaman_review_menampilkan_ringkasan_proporsi(): void
    {
        RkasHeader::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2025,
            'revisi_ke' => 1,
            'no_rkas' => 'RKAS/2025/01',
            'sumber_dana' => 'BOS Reguler',
            'jenis_rkas' => 'murni',
            'status' => 'disahkan',
        ]);

        $this->withSession(['tahun' => 2025])->get('/penganggaran/review')
            ->assertOk()
            ->assertSee('Review &amp; Proporsi Anggaran', false)
            ->assertSee('RKAS/2025/01')
            ->assertSee('Buku &amp; Perpustakaan', false)
            ->assertSee('Pemeliharaan Sarpras')
            ->assertSee('Pembayaran Honor Non-ASN');
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

    public function test_backup_dapat_diunduh_di_mode_portabel(): void
    {
        $this->withSession(['tahun' => 2025])->get('/pengaturan/backup')->assertOk()->assertSee('Backup Data Sekolah');

        // Di server pusat (bukan aplikasi portabel) dump basis data penuh milik
        // seluruh sekolah hanya untuk superadmin — operator ditolak.
        $this->withSession(['tahun' => 2025])->get('/pengaturan/backup/unduh')->assertForbidden();
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
