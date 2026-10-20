<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Services\ArkasImportService;
use App\Services\KasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Laporan resmi format ARKAS — semua halaman harus terbuka & berisi data hasil impor. */
class LaporanArkasTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private string $mini;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mini = base_path('tests/Fixtures/arkas-mini.xlsx');
        $s = School::create(['npsn' => '90000001', 'nama' => 'SD E', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->op = User::create(['name' => 'Op E', 'email' => 'ope@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);
        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'Honor Pengelola', 'tipe' => 'belanja']);
        Rekening::firstOrCreate(['kode' => '5.1.02.02.01.0001'], ['nama' => 'Honor Jasa', 'tipe' => 'belanja']);
        RekeningPajak::firstOrCreate(['kode' => 'pph21'], ['nama' => 'PPh 21']);
        for ($i = 1; $i <= 5; $i++) {
            Stage::create(['tahun' => 2025, 'nama' => 'Tahap '.$i, 'urutan' => $i, 'is_open' => true]);
        }
        $this->actingAs($this->op);
        ArkasImportService::import($this->mini, $s->id, $this->op->id, 2025);
    }

    private function ambil(string $uri): TestResponse
    {
        return $this->withSession(['tahun' => 2025])->get($uri);
    }

    public function test_semua_halaman_laporan_arkas_terbuka(): void
    {
        foreach ([
            'laporan-arkas/rkas-bulanan', 'laporan-arkas/rkas-tahap', 'laporan-arkas/realisasi',
            'laporan-arkas/penerimaan-belanja', 'laporan-arkas/habis-pakai', 'laporan-arkas/modal',
            'laporan-arkas/daftar-kas-umum', 'laporan-arkas/daftar-nota', 'laporan-arkas/penutupan-kas',
        ] as $u) {
            $this->ambil($u)->assertOk();
        }
    }

    public function test_rkas_bulanan_menampilkan_program_kegiatan_dan_status(): void
    {
        $h = $this->ambil('/laporan-arkas/rkas-bulanan');
        $h->assertOk();
        $h->assertSee('Rencana Kegiatan Anggaran Sekolah (RKAS) - Bulanan');
        $h->assertSee('03. Pengembangan Standar Proses');
        $h->assertSee('03.03.06. Kepramukaan');
        // Bulan tanpa belanja RKAS item → Belum; kolom Belanja & Total tampil.
        $this->ambil('/laporan-arkas/rkas-bulanan?bulan=3')->assertOk();
    }

    public function test_rkas_tahap_membagi_anggaran_per_tahap_volume(): void
    {
        $h = $this->ambil('/laporan-arkas/rkas-tahap');
        $h->assertOk();
        $h->assertSee('Tahap 1');
        $h->assertSee('Tahap 5');
        $html = $h->getContent();
        // RAPBS-1: jumlah 1.000.000, volume tahap 1 dari 2 → Tahap 1 penuh 1jt.
        $this->assertStringContainsString('1.000.000', $html);
    }

    public function test_realisasi_menampung_belanja_bulan_ini(): void
    {
        $h = $this->ambil('/laporan-arkas/realisasi?bulan=2');
        $h->assertOk();
        $html = $h->getContent();
        $this->assertStringContainsString('Realisasi s.d. Bulan Lalu', $html);
        $this->assertStringContainsString('5.000.000', $html); // Belanja ATK bulan 2
        // Hasil penggabungan laporan native: kolom Komponen + % + tombol CSV.
        $this->assertStringContainsString('Komponen', $html);
        $this->assertStringContainsString('%', $html);
        $this->assertStringContainsString('Ekspor CSV', $html);
        // CSV unduhan berisi kolom lengkap.
        $csv = $this->ambil('/laporan-arkas/realisasi.csv?bulan=2');
        $csv->assertOk();
        $this->assertStringContainsString('Kode Rekening', (string) $csv->getContent());
        // Laporan native lama sudah dimatikan (fungsinya pindah ke sini).
        $this->ambil('/laporan/realisasi')->assertNotFound();
        // Menu tak lagi punya dua halaman realisasi.
        $this->ambil('/pembukuan/bku')->assertOk()
            ->assertSee('Detail Nota: Daftar Kas Umum');
    }

    public function test_daftar_nota_memuat_tokodan_npwp(): void
    {
        $h = $this->ambil('/laporan-arkas/daftar-nota?bulan=2');
        $h->assertOk();
        $h->assertSee('Toko ATK');
        $h->assertSee('123456789012345');
        $h->assertSee('N-2025-01');
    }

    public function test_daftar_kas_umum_memuat_kegiatan_dan_penerima(): void
    {
        $h = $this->ambil('/laporan-arkas/daftar-kas-umum?bulan=2');
        $h->assertOk();
        $h->assertSee('Belanja ATK');
        $h->assertSee('Toko ATK');
        $h->assertSee('03.03.06. Kepramukaan');
    }

    public function test_register_penutupan_kas_dan_cetaknya(): void
    {
        $h = $this->ambil('/laporan-arkas/penutupan-kas?bulan=2');
        $h->assertOk();
        $h->assertSee('Saldo Buku Kas Umum');
        $h->assertSee('Keping Uang Logam');
        $h2 = $this->ambil('/laporan-arkas/penutupan-kas/cetak?bulan=2025-02');
        $h2->assertOk();
        $h2->assertSee('BERITA ACARA PEMERIKSAAN KAS');
    }

    public function test_bku_kolom_format_arkas(): void
    {
        $h = $this->ambil('/pembukuan/bku?bulan=2025-02');
        $h->assertOk();
        $h->assertSee('Kegiatan');
        $h->assertSee('Rekening');
        $h->assertSee('No. Bukti');
    }

    public function test_paket_dokumen_spj_lengkap_dengan_penyedia_dari_nota(): void
    {
        $belanja = KasTransaction::where('school_id', $this->op->school_id)
            ->where('type', 'belanja')->orderBy('id')->first();

        $h = $this->ambil('/pengeluaran/belanja/'.$belanja->id.'/spj');
        $h->assertOk();
        // Lima dokumen dalam satu paket, urut lembar format ARKAS.
        $h->assertSee('SURAT PESANAN (SP)');
        $h->assertSee('DOKUMEN BERITA ACARA NEGOSIASI');
        $h->assertSee('SURAT HASIL PEMERIKSAAN');
        $h->assertSee('BERITA ACARA SERAH TERIMA');
        $h->assertSee('DOKUMEN PENGADAAN BARANG DAN JASA');
        // Identitas satdik + penyedia hasil cocokkan nota ARKAS.
        $h->assertSee('SD E');
        $h->assertSee('Toko ATK');
        // Terbilang total pembayaran.
        $this->assertMatchesRegularExpression('/Terbilang: .+ rupiah/i', $h->getContent());
        // Route ikut disebut dari halaman belanja.
        $this->ambil('/pengeluaran/belanja/'.$belanja->id)->assertSee('Paket Dokumen SPJ');
    }

    public function test_format_bku_semester_buku_pembantu_dan_rekap(): void
    {
        $h = $this->ambil('/pembukuan/buku-pembantu?semester=1');
        $h->assertOk();
        $h->assertSee('BUKU PEMBANTU RINCIAN OBJEK BELANJA');
        $h->assertSee('Semester 1 (Januari–Juni)');
        // Belanja riil tampil dengan saldo kumulatif.
        $h->assertSee('Tanggal')->assertSee('Pengeluaran')->assertSee('Saldo');
        $this->ambil('/pembukuan/buku-pembantu?semester=2')->assertSee('Semester 2 (Juli–Desember)');

        $h2 = $this->ambil('/pembukuan/rekap-realisasi?semester=2');
        $h2->assertOk();
        $h2->assertSee('REKAPITULASI REALISASI BELANJA');
        $h2->assertSee('Anggaran')->assertSee('Realisasi Semester')->assertSee('Sisa Pagu');
        $this->ambil('/pembukuan/rekap-realisasi?semester=1')->assertOk();
        // Semester tak dikenal ditolak.
        $this->ambil('/pembukuan/buku-pembantu?semester=3')->assertNotFound();
    }

    public function test_bunga_bank_tersimpan_as_penerimaan_insidental(): void
    {
        $stage = Stage::where('is_open', true)->orderBy('urutan')->first();
        $sebelum = KasService::saldo($this->op->school_id)['bank'];
        $h = $this->withSession(['tahun' => 2025])->post('/penerimaan/bunga', [
            'tanggal' => '2025-03-05', 'nilai' => 25000,
            'bos_type_id' => BosType::first()->id, 'stage_id' => $stage->id,
        ]);
        $h->assertRedirect()->assertSessionHas('ok');

        $t = KasTransaction::where('type', 'pendapatan')
            ->where('keterangan', 'like', 'Bunga Bank%')->first();
        $this->assertNotNull($t);
        $this->assertSame(25000, (int) $t->total);
        $this->assertSame('Bunga Bank Maret 2025', $t->keterangan);
        // muncul di riwayat pendapatan
        $this->ambil('/penerimaan/pendapatan')->assertSee('Bunga Bank');
        // saldo kas sekolah naik tepat 25.000
        $this->assertSame($sebelum + 25000, (int) KasService::saldo($this->op->school_id)['bank']);
    }
}
