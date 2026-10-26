<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BkuTutupBulan;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BKU per bulan ala ARKAS 4:
 *  - tutup/buka BKU satu bulan (buka kembali hanya Kepala Sekolah),
 *  - transaksi pada bulan yang ditutup diblokir (pendapatan, belanja, hapus),
 *  - penerimaan melebihi pagu diberi peringatan (tidak memblokir),
 *  - halaman BKU memuat panel kontrol dana + grid 12 bulan.
 */
class BkuBulanTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private User $kepsek;

    private int $sid;

    private Stage $tahap1;

    private int $bosId;

    private int $rekPendapatan;

    private int $rekBelanja;

    protected function setUp(): void
    {
        parent::setUp();
        $s = School::create(['npsn' => '90000042', 'nama' => 'SD Bulan', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->sid = $s->id;
        $this->op = User::create(['name' => 'Op B', 'email' => 'op-bulan@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);
        $this->kepsek = User::create(['name' => 'Kep B', 'email' => 'kep-bulan@t.t', 'password' => 'p',
            'role' => 'kepsek', 'school_id' => $s->id]);
        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;
        $this->rekPendapatan = Rekening::firstOrCreate(['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Provinsi', 'tipe' => 'pendapatan'])->id;
        $this->rekBelanja = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'],
            ['nama' => 'Honor Pengelola', 'tipe' => 'belanja'])->id;
        $this->tahap1 = Stage::create(['tahun' => 2025, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Allocation::create(['school_id' => $s->id, 'tahun' => 2025, 'bos_type_id' => $this->bosId,
            'stage_id' => null, 'nilai' => 10_000_000]);
        $this->actingAs($this->op);
    }

    /** Kirim POST pendapatan (sesi tahun 2025). */
    private function kok(string $tanggal, int $nilai)
    {
        return $this->withSession(['tahun' => 2025])->post(route('pendapatan.store'), [
            'tanggal' => $tanggal, 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap1->id,
            'keterangan' => 'Pencairan tahap 1 '.$tanggal,
            'rekening_id' => [$this->rekPendapatan], 'nilai' => [$nilai],
        ]);
    }

    public function test_tutup_bulan_memblokir_pencatatan_dan_hapus(): void
    {
        $this->kok('2025-01-10', 5_000_000)->assertSessionHas('ok');
        $this->assertDatabaseCount('kas_transactions', 1);

        // Tutup BKU Januari 2025 (ala ARKAS)
        $this->withSession(['tahun' => 2025])->post(route('bku.tutup'), ['bulan' => '2025-01'])
            ->assertRedirect(route('bku', ['bulan' => '2025-01']))->assertSessionHas('ok');
        $this->assertDatabaseHas('bku_tutup_bulans', ['bulan' => '2025-01']);

        // Catatan baru pada bulan yang sama ditolak
        $this->kok('2025-01-20', 5_000_000)->assertSessionHas('error');
        $this->assertDatabaseCount('kas_transactions', 1);

        // Belanja pun diblokir
        $this->withSession(['tahun' => 2025])->post(route('belanja.store'), [
            'tanggal' => '2025-01-25', 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap1->id,
            'metode_bayar' => 'tunai', 'keterangan' => 'Belanja Januari',
            'rekening_id' => [$this->rekBelanja], 'nilai' => [1_000_000],
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('kas_transactions', 1);

        // Hapus baris lampau diblokir
        $trx = KasTransaction::first();
        $this->withSession(['tahun' => 2025])->delete(route('penerimaan.destroy', $trx))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('kas_transactions', 1);
    }

    public function test_buka_kembali_bulan_khusus_kepsek(): void
    {
        $this->kok('2025-01-10', 5_000_000); // tutup bulan menuntut ada transaksi pada tahun itu
        $this->withSession(['tahun' => 2025])->post(route('bku.tutup'), ['bulan' => '2025-01']);
        $this->assertDatabaseHas('bku_tutup_bulans', ['bulan' => '2025-01']);

        // Operator tidak boleh membuka kembali
        $this->withSession(['tahun' => 2025])->post(route('bku.buka'), ['bulan' => '2025-01'])
            ->assertSessionHas('error');
        $this->assertDatabaseHas('bku_tutup_bulans', ['bulan' => '2025-01']);

        // Kepala Sekolah membuka kembali → catatan bisa masuk lagi. Simulasikan
        // sesi KS tersendiri karena klien test sebelumnya bertindak sebagai operator.
        $this->actingAs($this->kepsek);
        $this->withSession([
            'tahun' => 2025,
            'password_hash_web' => (string) $this->kepsek->getAuthPassword(),
        ])->post(route('bku.buka'), ['bulan' => '2025-01'])
            ->assertSessionHas('ok');
        $this->assertDatabaseMissing('bku_tutup_bulans', ['bulan' => '2025-01']);

        $this->kok('2025-01-21', 6_000_000)->assertSessionHas('warning'); // 11 jt > pagu 10 jt
        $this->assertDatabaseCount('kas_transactions', 2);
    }

    public function test_penerimaan_melebihi_pagu_diberi_peringatan(): void
    {
        $this->kok('2025-02-05', 5_000_000)->assertSessionHas('ok')->assertSessionMissing('warning');

        // Total 13 jt melewati pagu 10 jt → peringatan (tetap tersimpan)
        $this->kok('2025-02-10', 8_000_000)->assertSessionHas('warning');
        $this->assertDatabaseCount('kas_transactions', 2);
        $this->assertSame(13_000_000, (int) KasTransaction::where('tahun', 2025)->where('type', 'pendapatan')->sum('total'));
    }

    /** Grid 12 bulan ala ARKAS 4: pemilih bulan utama, 4 kolom x 3 baris. */
    public function test_grid_dua_belas_bulan_tampil_di_atas_dengan_status_arkas(): void
    {
        $this->kok('2025-03-05', 5_000_000);

        $html = $this->withSession(['tahun' => 2025])
            ->get(route('bku', ['bulan' => '2025-03']))
            ->assertOk()
            ->getContent();

        foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $nama) {
            $this->assertStringContainsString($nama, $html, 'Kotak bulan '.$nama.' tidak ada.');
        }

        // Grid harus muncul sebelum tabel BKU (pemilih utama, bukan pelengkap di bawah).
        $posGrid = mb_strpos($html, 'pilih bulan');
        $posTabel = mb_strpos($html, 'Buku Kas Umum</div>');
        $this->assertNotFalse($posGrid);
        if ($posTabel !== false) {
            $this->assertLessThan($posTabel, $posGrid, 'Grid bulan harus berada di atas tabel BKU.');
        }

        // Bulan yang dipilih ditandai aria-current, dan bulan tertutup diberi tanda centang.
        $this->assertStringContainsString('aria-current="true"', $html);
    }

    public function test_halaman_bku_panel_kendali_dan_buka_kembali(): void
    {
        $this->kok('2025-03-05', 5_000_000);
        $this->withSession(['tahun' => 2025])->get(route('bku', ['bulan' => '2025-03']))
            ->assertOk()
            ->assertSee('Kontrol Dana Tahun 2025')
            ->assertSee('Status BKU per Bulan 2025')
            ->assertSee('Dana Tersedia')
            ->assertSee('Bisa Dianggarkan Ulang');

        $this->withSession(['tahun' => 2025])->post(route('bku.tutup'), ['bulan' => '2025-03']);

        // Operator melihat status tertutup TANPA tombol buka kembali
        $this->withSession(['tahun' => 2025])->get(route('bku', ['bulan' => '2025-03']))
            ->assertOk()->assertSee('TERTUTUP')->assertDontSee('Buka Kembali');

        // Kepala Sekolah melihat tombol buka kembali. Sesi berbeda dari operator.
        $this->actingAs($this->kepsek);
        $this->withSession([
            'tahun' => 2025,
            'password_hash_web' => (string) $this->kepsek->getAuthPassword(),
        ])->get(route('bku', ['bulan' => '2025-03']))
            ->assertOk()->assertSee('Buka Kembali');
    }

    public function test_model_kunci_bulan_dan_bulan_terkunci(): void
    {
        $this->assertFalse(BkuTutupBulan::terkunciBulan($this->sid, '2025-04'));
        BkuTutupBulan::create(['tahun' => 2025, 'bulan' => '2025-04', 'ditutup_oleh' => 't']);
        $this->assertTrue(BkuTutupBulan::terkunciBulan($this->sid, '2025-04'));
        $this->assertFalse(BkuTutupBulan::terkunciBulan($this->sid, '2025-05'));
        $this->assertFalse(BkuTutupBulan::terkunciBulan($this->sid + 1, '2025-04')); // sekolah lain tak terkunci
        $this->assertSame(['2025-04'], BkuTutupBulan::bulanTerkunci($this->sid, 2025));
    }

    /**
     * Aksi cepat di lembar BKU (sisa P1-5): catat dana masuk atau belanja tanpa
     * pindah menu, seperti tombol di lembar BKU ARKAS 4.
     *
     * Tombolnya tautan biasa — bukan handler atribut inline — karena CSP
     * memasang `script-src-attr 'none'`.
     */
    public function test_lembar_bku_memuat_aksi_cepat_penerimaan_dan_belanja(): void
    {
        $html = $this->withSession(['tahun' => 2025])
            ->get('/pembukuan/bku?bulan=2025-02')->assertOk()->getContent();

        $this->assertStringContainsString('Tambah Penerimaan Dana', $html);
        $this->assertStringContainsString('href="'.route('pendapatan.index').'"', $html);
        $this->assertStringContainsString('Tambah Pembelanjaan', $html);
        $this->assertStringContainsString('href="'.route('belanja.create').'"', $html);

        // Keduanya harus tautan, bukan tombol ber-onclick (patuh CSP).
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*\son(click|change)=/i', $html);
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*penerimaan\/pendapatan"[^>]*class="btn"[^>]*>\s*➕?\s*Tambah Penerimaan Dana/',
            $html, 'Aksi "Tambah Penerimaan Dana" harus tautan bergaya .btn.');
    }

    /** Saat bulan sudah ditutup, aksi cepat disembunyikan (selaras Tutup BKU). */
    public function test_aksi_cepat_hilang_pada_bulan_terkunci(): void
    {
        $this->kok('2025-01-10', 5_000_000);
        $this->withSession(['tahun' => 2025])->post(route('bku.tutup'), ['bulan' => '2025-01'])
            ->assertSessionHas('ok');

        $terkunci = $this->withSession(['tahun' => 2025])
            ->get('/pembukuan/bku?bulan=2025-01')->assertOk()->getContent();
        $this->assertStringNotContainsString('Tambah Penerimaan Dana', $terkunci);
        $this->assertStringNotContainsString('Tambah Pembelanjaan', $terkunci);

        // Bulan yang masih terbuka tetap menampilkan aksi cepat.
        $terbuka = $this->withSession(['tahun' => 2025])
            ->get('/pembukuan/bku?bulan=2025-02')->assertOk()->getContent();
        $this->assertStringContainsString('Tambah Penerimaan Dana', $terbuka);
        $this->assertStringContainsString('Tambah Pembelanjaan', $terbuka);
    }
}
