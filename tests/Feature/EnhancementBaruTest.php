<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Services\EarlyWarningService;
use App\Services\KonsolidasiExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancementBaruTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private User $dinas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '69888123',
            'nama' => 'SD Negeri Binaan 1',
            'jenjang' => 'SD',
            'kecamatan' => 'Kepanjen',
            'kab_kota' => 'Malang',
            'provinsi' => 'Jawa Timur',
            'is_active' => true,
        ]);

        $this->operator = User::create([
            'name' => 'Bendahara SD',
            'email' => 'bendahara@sd1.sch.id',
            'password' => bcrypt('password123'),
            'role' => 'operator',
            'school_id' => $this->sekolah->id,
        ]);

        $this->dinas = User::create([
            'name' => 'Admin Dinas Pendidikan',
            'email' => 'dinas@malang.go.id',
            'password' => bcrypt('password123'),
            'role' => 'dinas',
        ]);

        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
    }

    public function test_dashboard_menampilkan_stepper_alur_bosp(): void
    {
        $response = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Alur Siklus Kerja Dana BOSP');
        $response->assertSee('Tarik ARKAS / RKAS');
        $response->assertSee('Lengkapi Kuitansi/Nota');
        $response->assertSee('Setor Pajak & NTPN');
        $response->assertSee('Rekonsiliasi & Tutup BKU');
        $response->assertSee('Cetak LPJ & Sinkron');
    }

    public function test_early_warning_mendeteksi_belanja_besar_tanpa_pajak(): void
    {
        $bos = BosType::first();
        $stage = Stage::create([
            'tahun' => 2026,
            'nama' => 'Tahap 1',
            'urutan' => 1,
            'is_open' => true,
        ]);

        $rek = Rekening::firstOrCreate(
            ['kode' => '5.1.02.01.01.0024'],
            ['nama' => 'Belanja Alat Tulis Kantor', 'tipe' => 'belanja']
        );

        // Belanja Rp 2.500.000 tanpa pajak
        $trx = KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'stage_id' => $stage->id,
            'bos_type_id' => $bos->id,
            'tanggal' => '2026-03-10',
            'no_bku' => 1,
            'keterangan' => 'Pembelian ATK Kantor Besar',
            'total' => 2500000,
            'metode_bayar' => 'tunai',
        ]);

        $warnings = EarlyWarningService::cekKepatuhan($this->sekolah->id, 2026);
        $this->assertGreaterThanOrEqual(1, $warnings['total_peringatan']);

        $ketemuPajak = false;
        foreach ($warnings['items'] as $item) {
            if ($item['kategori'] === 'pajak' && str_contains($item['judul'], '≥ Rp 2.000.000')) {
                $ketemuPajak = true;
                break;
            }
        }
        $this->assertTrue($ketemuPajak, 'Peringatan belanja >= 2 juta tanpa pajak harus terdeteksi');

        // Dan muncul di dashboard
        $response = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Deteksi Validasi');
        $response->assertSee('Tanpa Pungutan Pajak');
    }

    public function test_batch_spj_generator_berhasil_diakses(): void
    {
        $stage = Stage::create([
            'tahun' => 2026,
            'nama' => 'Tahap 1',
            'urutan' => 1,
            'is_open' => true,
        ]);

        KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'stage_id' => $stage->id,
            'tanggal' => '2026-03-15',
            'no_bku' => 2,
            'keterangan' => 'Belanja Kertas HVS',
            'total' => 450000,
            'metode_bayar' => 'tunai',
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get('/pengeluaran/belanja/cetak-batch?bulan=2026-03');

        $response->assertOk();
        $response->assertSee('KWITANSI PEMBAYARAN');
        $response->assertSee('Belanja Kertas HVS');
        $response->assertSee('SD Negeri Binaan 1');
    }

    public function test_dinas_dashboard_memiliki_filter_kepatuhan_dan_ekspor(): void
    {
        $response = $this->actingAs($this->dinas)
            ->withSession(['tahun' => 2026])
            ->get('/dinas');

        $response->assertOk();
        $response->assertSee('Peta Monitor Kepatuhan');
        $response->assertSee('Belum Salur');
        $response->assertSee('Realisasi Tahap 1 &lt; 50%', false);
        $response->assertSee('Menunggu Verifikasi LPJ');
        $response->assertSee('Unduh Konsolidasi Wilayah (Excel Multi-Sheet)');

        // Test ekspor konsolidasi menghasilkan berkas xlsx
        $path = KonsolidasiExportService::xlsx(collect([$this->sekolah]), 2026);
        $this->assertFileExists($path);
        @unlink($path);
    }

    public function test_halaman_deteksi_versi_memuat_tombol_tarik_dan_sinkron_tanpa_ada_di_sidebar(): void
    {
        // 1. Pada halaman deteksi versi, aksi Tarik Data ARKAS & Sinkron ke Pusat harus tersedia
        $response = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get('/pengaturan/deteksi-versi');

        $response->assertOk();
        $response->assertSee('Tarik Data dari ARKAS');
        $response->assertSee('Kirim / Sinkron ke Pusat');
        $response->assertSee(route('pengaturan.sinkronisasi.jalankan'));

        // 2. Pada sidebar (misal di halaman dashboard), tombol form tarik dan sinkron sudah tidak boleh ada
        $dashboardHtml = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('tarik-arkas.tarik'), $dashboardHtml,
            'Tombol Tarik Data ARKAS tidak boleh ada di sidebar.');
        $this->assertStringNotContainsString(route('pengaturan.sinkronisasi.jalankan'), $dashboardHtml,
            'Tombol Kirim / Sinkron ke Pusat tidak boleh ada di sidebar.');
    }
}
