<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\School;
use App\Models\User;
use App\Services\BkuBulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penatausahaan ala ARKAS 4: sidebar hanya satu tautan, rincian menu ada di
 * halaman (status BKU 12 bulan + kartu jalan masuk ke setiap fitur).
 *
 * Yang diuji di sini:
 *  - status 12 bulan memakai satu sumber logika (BkuBulan) dan hasilnya benar,
 *  - halaman menampilkan 12 bulan, ringkasan sumber dana, dan tautan fitur,
 *  - sidebar tidak lagi memuat sub-menu penatausahaan (tidak ada tautan ganda).
 */
class PenatausahaanIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private int $sid;

    private int $bosId;

    private int $bosDaerah;

    protected function setUp(): void
    {
        parent::setUp();
        $s = School::create(['npsn' => '90000077', 'nama' => 'SD Penatausahaan', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->sid = $s->id;
        $this->op = User::create(['name' => 'Op P', 'email' => 'op-penatausahaan@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);
        $this->actingAs($this->op);

        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;
        $this->bosDaerah = BosType::firstOrCreate(['kode' => 'daerah'], ['nama' => 'BOS Daerah'])->id;

        Allocation::create(['school_id' => $this->sid, 'tahun' => 2025, 'bos_type_id' => $this->bosId,
            'stage_id' => null, 'nilai' => 10_000_000]);
        Allocation::create(['school_id' => $this->sid, 'tahun' => 2025, 'bos_type_id' => $this->bosDaerah,
            'stage_id' => null, 'nilai' => 4_000_000]);

        // Februari: penerimaan, Maret: belanja. Januari sengaja kosong.
        $this->transaksi('2025-02-10', 'pendapatan', 10_000_000, $this->bosId);
        $this->transaksi('2025-03-05', 'belanja', 2_500_000, $this->bosId);
    }

    private function transaksi(string $tanggal, string $type, int $total, int $bosId): void
    {
        KasTransaction::create([
            'school_id' => $this->sid, 'tahun' => (int) substr($tanggal, 0, 4), 'type' => $type,
            'tanggal' => $tanggal, 'bos_type_id' => $bosId, 'metode_bayar' => 'tunai',
            'keterangan' => ucfirst($type).' '.$tanggal, 'total' => $total,
        ]);
    }

    /** Status per bulan dihitung di satu tempat (BkuBulan) dan hasilnya sesuai aturan ARKAS. */
    public function test_status_dua_belas_bulan_sesuai_aturan_arkas(): void
    {
        BkuTutupBulan::create(['school_id' => $this->sid, 'tahun' => 2025, 'bulan' => '2025-02']);

        $peta = collect(BkuBulan::grid($this->sid, 2025, '2025-03'))
            ->keyBy('ym');

        $this->assertCount(12, $peta);
        $this->assertSame('tanpa-dana', $peta['2025-01']['status'], 'Januari kosong sebelum dana masuk.');
        $this->assertSame('tertutup', $peta['2025-02']['status'], 'Februari sudah ditutup.');
        $this->assertSame('draf', $peta['2025-03']['status'], 'Maret = bulan berjalan.');
        $this->assertSame('kosong', $peta['2025-04']['status'], 'April belum ada transaksi.');
        $this->assertSame(1, $peta['2025-02']['n'], 'Jumlah transaksi Februari terbaca.');
        $this->assertTrue($peta['2025-03']['now'], 'Penanda bulan berjalan hanya di Maret.');
    }

    /** Halaman perantara terbuka dan memuat grid 12 bulan + ringkasan sumber dana. */
    public function test_halaman_menampilkan_grid_bulan_dan_sumber_dana(): void
    {
        BkuTutupBulan::create(['school_id' => $this->sid, 'tahun' => 2025, 'bulan' => '2025-02']);

        $r = $this->withSession(['tahun' => 2025])->get(route('penatausahaan.index'));

        $r->assertOk();
        $r->assertSee('Penatausahaan 2025');
        $r->assertSee('Status BKU 2025');
        $r->assertSee('1 dari 12 bulan sudah ditutup.');

        // Ke-12 bulan tampil
        foreach (BkuBulan::NAMA as $nama) {
            $r->assertSee($nama);
        }

        // Status tertutup & draf/selesai punya label sendiri
        $r->assertSee('Sudah Selesai');
        $r->assertSee('Belum Dibuat');

        // Ringkasan pagu per sumber dana
        $r->assertSee('BOS Reguler');
        $r->assertSee('BOS Daerah');
        $r->assertSee('Rp 10.000.000');
        $r->assertSee('Rp 2.500.000');

        // Klik bulan membuka BKU bulan itu
        $r->assertSee(route('bku', ['bulan' => '2025-02']), false);
    }

    /** Setiap fitur penatausahaan tetap terjangkau dari halaman ini. */
    public function test_semua_fitur_masih_terjangkau_dari_halaman(): void
    {
        $r = $this->withSession(['tahun' => 2025])->get(route('penatausahaan.index'));
        $r->assertOk();

        $tujuan = [
            route('bku'), route('bku.cetak'), route('kas'),
            route('pendapatan.index'), route('belanja.index'), route('belanja.create'),
            route('setorpajak.index'), route('ambil.index'), route('mutasi.index'),
            route('pembukuan.bukuPembantu'), route('pembukuan.rekapRealisasi'),
            route('pembukuan.register', ['jenis' => 'tunai']), route('pembukuan.sptjm'),
            route('penatausahaan.penutupan-bku'),
        ];
        foreach ($tujuan as $url) {
            $r->assertSee($url, false);
        }
    }

    /** Sidebar: area kerja Penatausahaan kini satu tautan, tanpa sub-menu. */
    public function test_sidebar_penatausahaan_satu_tautan(): void
    {
        $html = $this->withSession(['tahun' => 2025])->get(route('dashboard'))->assertOk()->getContent();

        preg_match('#<div class="navw-utama">(.*?)</div>\s*<div class="navw-sep"#s', $html, $utama);
        $blok = $utama[1] ?? '';
        $this->assertNotSame('', $blok, 'Blok navw-utama tidak ditemukan.');

        $this->assertStringContainsString('>Penatausahaan</a>', $blok,
            'Penatausahaan harus satu tautan biasa, bukan tombol grup.');
        $this->assertStringContainsString(route('penatausahaan.index'), $blok);

        // Sub-menu lama tidak boleh lagi ada di sidebar (hanya di halaman).
        foreach (['Buku Kas Umum', 'Tarik Tunai (Bank→Tunai)', 'Setor Tunai (Tunai→Bank)',
            'Pengeluaran &amp; Pajak', 'Pelaporan'] as $hilang) {
            $this->assertStringNotContainsString($hilang, $blok,
                'Sub-menu "'.$hilang.'" masih ada di sidebar.');
        }
    }
}
