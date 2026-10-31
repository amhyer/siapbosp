<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BkuTutupBulan;
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
        $this->assertMatchesRegularExpression('#>Dashboard</a>#', $nav, 'Dashboard bukan tautan langsung.');

        // ARKAS 4: sidebar tanpa ikon/emoji — label menu teks saja.
        $this->assertDoesNotMatchRegularExpression(
            '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $nav,
            'Sidebar ARKAS 4 tanpa ikon; label menu harus berupa teks saja.');
        $this->assertStringNotContainsString('Beranda', $nav, 'Induk menu Beranda seharusnya sudah dihapus.');
        $this->assertStringNotContainsString('Tahun Anggaran', $nav,
            'Data Sekolah seharusnya pindah ke dropdown profil, bukan sidebar.');
    }

    /** Dropdown profil ARKAS 4 hanya memuat Data Penanggung Jawab + Keluar. */
    public function test_dropdown_profil_hanya_data_penanggung_jawab_dan_keluar(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('penandatangan').'"', $html);
        $this->assertStringContainsString('Data Penanggung Jawab', $html);
        $this->assertStringContainsString('action="'.route('logout').'"', $html, 'Keluar harus ada di dropdown profil.');

        // Data sekolah tidak lagi di dropdown — ARKAS 4 hanya punya dua item.
        foreach ([route('profil.edit'), route('datasekolah.tahun'), route('datasekolah.sumber-dana'), route('datasekolah.sinkronisasi')] as $href) {
            $this->assertStringNotContainsString('href="'.$href.'"', $html,
                'Dropdown profil ARKAS 4 tidak memuat tautan data sekolah.');
        }

        // Pintasan data sekolah ada di halaman Data Penanggung Jawab.
        $akun = $this->withSession(['tahun' => 2025])->get(route('penandatangan'))->assertOk()->getContent();
        $this->assertStringContainsString('Data Sekolah', $akun);
        foreach ([
            route('profil.edit') => 'Profil Sekolah',
            route('datasekolah.tahun') => 'Tahun Anggaran',
            route('datasekolah.sumber-dana') => 'Sumber Dana',
            route('datasekolah.sinkronisasi') => 'Status Sinkronisasi ke Pusat',
        ] as $href => $label) {
            $this->assertStringContainsString('href="'.$href.'"', $akun, $label.' tidak ada di halaman Data Penanggung Jawab.');
        }
    }

    /** Blok utilitas bawah sidebar (padanan Tutorial & Perbarui Aplikasi ARKAS 4). */
    public function test_utilitas_bawah_sidebar_dan_halaman_bantuan(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('navw-util', $html);
        foreach (['Tutorial', 'Pertanyaan Umum', 'Hubungi Kami', 'Perbarui Aplikasi'] as $label) {
            $this->assertStringContainsString($label, $html, 'Utilitas '.$label.' tidak ada di sidebar.');
        }

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
        preg_match_all('#<a href="([^"]+)"([^>]*)>([^<]+)</a>#', $nav, $link, PREG_SET_ORDER);

        $target = [];
        foreach ($link as $l) {
            // Judul area kerja (Penganggaran/Penatausahaan/Arsip) sengaja
            // menuju halaman pertama submenunya, jadi duplikat itu sah.
            if (str_contains($l[2], 'navw-area')) {
                continue;
            }
            $href = html_entity_decode($l[1]);
            $label = trim($l[3]);
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

    /** Arsip terpadu ala ARKAS 4: 3 filter (format x sumber dana x tahun). */
    public function test_arsip_terpadu_dengan_tiga_filter_arkas(): void
    {
        RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2025, 'revisi_ke' => 1,
            'no_rkas' => 'RKAS/2025/ARSIP', 'sumber_dana' => 'BOS Reguler',
            'jenis_rkas' => 'murni', 'status' => 'disahkan',
        ]);
        RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2025, 'revisi_ke' => 1,
            'sumber_dana' => 'BOS Kinerja', 'jenis_rkas' => 'murni', 'status' => 'disahkan',
        ]);
        // Draf tidak boleh masuk arsip.
        RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2025, 'revisi_ke' => 1,
            'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni', 'status' => 'draft',
        ]);

        $this->withSession(['tahun' => 2025])->get(route('arsip.index'))
            ->assertOk()
            ->assertSee('Arsip Dokumen Sah')
            ->assertSee('Semua Format')
            ->assertSee('Semua sumber dana')
            ->assertSee('RKAS BOS Reguler 2025')
            ->assertSee('RKAS BOS Kinerja 2025')
            ->assertSee('Cetak');

        // Filter sumber dana
        $this->withSession(['tahun' => 2025])->get(route('arsip.index', ['sumber' => 'BOS Kinerja']))
            ->assertOk()->assertSee('RKAS BOS Kinerja 2025')->assertDontSee('RKAS BOS Reguler 2025');

        // Filter format: hanya BKU -> dokumen RKAS tidak muncul
        $this->withSession(['tahun' => 2025])->get(route('arsip.index', ['format' => 'bku']))
            ->assertOk()->assertDontSee('RKAS BOS Reguler 2025');

        // Filter tahun yang tidak punya dokumen
        $this->withSession(['tahun' => 2025])->get(route('arsip.index', ['tahun' => '2021']))
            ->assertOk()->assertSee('Belum ada dokumen sah');
    }

    /**
     * BKU yang sudah ditutup masuk arsip dengan nama bulan yang benar.
     * Regresi: kolom `bulan` berisi 'YYYY-MM' dan dulu di-cast (int) sehingga
     * dokumen tampil "BKU 2025 2025" dengan tautan ?bulan=2025-2025.
     */
    public function test_arsip_bku_menampilkan_nama_bulan_dan_tautan_yang_benar(): void
    {
        BkuTutupBulan::create(['school_id' => $this->sekolah->id, 'tahun' => 2025,
            'bulan' => '2025-03', 'ditutup_oleh' => 'operator']);

        $r = $this->withSession(['tahun' => 2025])
            ->get(route('arsip.index', ['format' => 'bku']))
            ->assertOk();

        $r->assertSee('BKU Maret 2025');
        $r->assertDontSee('BKU 2025 2025');
        $r->assertSee('bulan=2025-03', false);
        $r->assertDontSee('bulan=2025-2025', false);
    }

    public function test_arsip_hanya_memuat_dokumen_sah(): void
    {
        RkasHeader::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2025, 'revisi_ke' => 1,
            'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni', 'status' => 'dikirim_dinas',
        ]);

        $this->withSession(['tahun' => 2025])->get(route('arsip.index'))
            ->assertOk()
            ->assertDontSee('RKAS BOS Reguler 2025', false)
            ->assertSee('Belum ada dokumen sah');
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
