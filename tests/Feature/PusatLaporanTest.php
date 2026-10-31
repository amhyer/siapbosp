<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasDetail;
use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tahap 1 paritas ARKAS 4: Pusat Format Laporan, Buku Kas Pembantu Bank/Tunai,
 * Buku Pembantu Pajak, Rekapitulasi Realisasi multi-periode, SPTJM bulanan,
 * dan Realisasi Penerimaan & Belanja semester.
 */
class PusatLaporanTest extends TestCase
{
    use RefreshDatabase;

    private int $sid;

    private int $bosId;

    private int $noBku = 0;

    private Rekening $rekPendapatan;

    private Rekening $rekBelanja;

    private Stage $tahap1;

    private Stage $tahap2;

    private KasTransaction $belanjaFebruari;

    private KasTransaction $belanjaMaret;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::create([
            'npsn' => '40300001', 'nama' => 'SDN Pusat Laporan', 'jenjang' => 'SD',
            'kecamatan' => 'Rappocini', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->sid = $school->id;

        $op = User::create([
            'name' => 'Operator Laporan', 'email' => 'laporan@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $school->id,
        ]);

        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;

        $this->rekPendapatan = Rekening::firstOrCreate(
            ['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Pemerintah', 'tipe' => 'pendapatan']
        );
        $this->rekBelanja = Rekening::firstOrCreate(
            ['kode' => '5.1.02.01.01.0001'],
            ['nama' => 'Honor Pengelola', 'tipe' => 'belanja']
        );

        $this->tahap1 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $this->tahap2 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 2', 'urutan' => 2, 'is_open' => true]);

        RkasHeader::create([
            'school_id' => $school->id, 'tahun' => 2026, 'revisi_ke' => 1,
            'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni', 'status' => 'disahkan',
        ])->details()->create([
            'rekening_id' => $this->rekBelanja->id,
            'uraian' => 'Honor Pengelola Sekolah',
            'satuan' => 'paket', 'banyak' => 1, 'harga_satuan' => 20_000_000, 'jumlah' => 20_000_000,
            'sumber_dana' => 'BOS Reguler',
        ]);

        $this->actingAs($op);

        // Pola kas 2026: Jan +10jt; Feb -2jt +5jt; Mar tarik tunai 3jt lalu belanja tunai 1jt;
        // Jul +4jt; Agu -1,5jt.
        $this->transaksi('pendapatan', '2026-01-05', 10_000_000, 'transfer', $this->tahap1, 'Pencairan Tahap 1');
        $this->belanjaFebruari = $this->transaksi('belanja', '2026-02-10', 2_000_000, 'transfer', $this->tahap1, 'Belanja Tahap 1');
        $this->transaksi('pendapatan', '2026-02-20', 5_000_000, 'transfer', $this->tahap2, 'Pencairan Tahap 2');
        $this->transaksi('ambil_simpanan', '2026-03-01', 3_000_000, null, $this->tahap2, 'Tarik Tunai');
        $this->belanjaMaret = $this->transaksi('belanja', '2026-03-10', 1_000_000, 'tunai', $this->tahap2, 'Belanja Tunai');
        $this->transaksi('pendapatan', '2026-07-05', 4_000_000, 'transfer', $this->tahap2, 'Pencairan Tahap 2');
        $this->transaksi('belanja', '2026-08-10', 1_500_000, 'transfer', $this->tahap2, 'Belanja Tahap 2');

        $pph = RekeningPajak::create(['kode' => '411121', 'nama' => 'PPh Pasal 21']);
        KasTax::create([
            'kas_transaction_id' => $this->belanjaFebruari->id, 'rekening_pajak_id' => $pph->id,
            'nilai' => 200_000, 'status' => 'belum',
        ]);
        KasTax::create([
            'kas_transaction_id' => $this->belanjaMaret->id, 'rekening_pajak_id' => $pph->id,
            'nilai' => 100_000, 'status' => 'sudah', 'ntpn' => 'NTPN-TEST-001',
        ]);
    }

    private function transaksi(
        string $type,
        string $tanggal,
        int $nilai,
        ?string $metode,
        Stage $tahap,
        string $keterangan,
    ): KasTransaction {
        $this->noBku++;

        $trx = KasTransaction::create([
            'school_id' => $this->sid, 'tahun' => 2026, 'type' => $type,
            'no_bku' => $this->noBku, 'no_bukti' => 'B-'.$this->noBku, 'tanggal' => $tanggal,
            'bos_type_id' => $this->bosId, 'stage_id' => $tahap->id,
            'metode_bayar' => $metode, 'keterangan' => $keterangan, 'total' => $nilai,
        ]);

        if (in_array($type, ['pendapatan', 'belanja'], true)) {
            KasDetail::create([
                'kas_transaction_id' => $trx->id,
                'rekening_id' => $type === 'pendapatan' ? $this->rekPendapatan->id : $this->rekBelanja->id,
                'uraian' => $keterangan, 'nilai' => $nilai,
            ]);
        }

        return $trx;
    }

    private function halaman(string $url): TestResponse
    {
        return $this->withSession(['tahun' => 2026])->get($url)->assertOk();
    }

    public function test_pusat_format_laporan_menampilkan_semua_kelompok_dan_format_baru(): void
    {
        $r = $this->halaman(route('laporan.format', [
            'bulan' => 3, 'semester' => 1, 'tahap' => $this->tahap1->id,
        ]));

        $r->assertSee('Pusat Format Laporan')
            ->assertSee('Buku Kas Umum (BKU)')
            ->assertSee('Buku Pembantu')
            ->assertSee('Rekapitulasi & Realisasi')
            ->assertSee('Pertanggungjawaban')
            ->assertSee('Buku Kas Pembantu Bank')
            ->assertSee('Buku Kas Pembantu Tunai')
            ->assertSee('Buku Pembantu Pajak')
            ->assertSee('Rekapitulasi Realisasi')
            ->assertSee('SPTJM Dana BOSP')
            ->assertSee('Realisasi Penerimaan &amp; Belanja', false)
            ->assertSee('data-cetak', false)
            ->assertSee('pembukuan/buku-pembantu-bank', false)
            ->assertSee('pembukuan/buku-pembantu-tunai', false)
            ->assertSee('pembukuan/buku-pembantu-pajak', false)
            ->assertSee('rekap-realisasi', false)
            ->assertSee('periode=bulanan', false)
            ->assertSee('periode=semester', false);
    }

    public function test_buku_kas_pembantu_bank_bulanan_memakai_saldo_awal_dan_mutasi_bulan_itu(): void
    {
        $r = $this->halaman(route('pembukuan.bukuPembantuBank', [
            'periode' => 'bulan', 'bulan' => '2026-02',
        ]));

        $r->assertSee('BUKU KAS PEMBANTU BANK')
            ->assertSee('BULAN FEBRUARI 2026')
            ->assertSee('SALDO AWAL BULAN')
            ->assertSee('10/02/2026')
            ->assertSee('20/02/2026')
            ->assertDontSee('05/01/2026')
            ->assertDontSee('10/03/2026')
            ->assertSee('Rp 10.000.000')   // saldo awal dari Januari
            ->assertSee('Rp 13.000.000');  // saldo akhir Februari
    }

    public function test_buku_kas_pembantu_bank_tahunan_menjumlahkan_seluruh_tahun(): void
    {
        $r = $this->halaman(route('pembukuan.bukuPembantuBank', ['periode' => 'tahun']));

        $r->assertSee('TAHUN ANGGARAN 2026')
            ->assertSee('05/01/2026')
            ->assertSee('10/08/2026')
            ->assertSee('Rp 12.500.000'); // 10 - 2 + 5 - 3 + 4 - 1,5
    }

    public function test_buku_kas_pembantu_tunai_mencatat_tarik_tunai_dan_belanja_tunai(): void
    {
        $r = $this->halaman(route('pembukuan.bukuPembantuTunai', [
            'periode' => 'bulan', 'bulan' => '2026-03',
        ]));

        $r->assertSee('BUKU KAS PEMBANTU TUNAI')
            ->assertSee('01/03/2026')
            ->assertSee('10/03/2026')
            ->assertSee('Rp 3.000.000')  // tarik tunai masuk
            ->assertSee('Rp 1.000.000')  // belanja tunai keluar
            ->assertSee('Rp 2.000.000')  // saldo akhir tunai
            ->assertDontSee('05/01/2026');
    }

    public function test_buku_pembantu_pajak_bulanan_menampilkan_potongan_dan_status_setor(): void
    {
        $r = $this->halaman(route('pembukuan.bukuPembantuPajak', [
            'periode' => 'bulan', 'bulan' => '2026-02',
        ]));

        $r->assertSee('BUKU PEMBANTU PAJAK')
            ->assertSee('PPh Pasal 21')
            ->assertSee('Rp 200.000')
            ->assertSee('Belum setor')
            ->assertDontSee('NTPN-TEST-001');
    }

    public function test_buku_pembantu_pajak_tahunan_menjumlahkan_sudah_dan_belum_setor(): void
    {
        $r = $this->halaman(route('pembukuan.bukuPembantuPajak', ['periode' => 'tahun']));

        $r->assertSee('Rp 300.000')
            ->assertSee('Rp 200.000')
            ->assertSee('Rp 100.000')
            ->assertSee('Sudah setor')
            ->assertSee('NTPN-TEST-001');
    }

    public function test_rekap_realisasi_mendukung_bulanan_semester_tahunan_dan_tahapan(): void
    {
        $this->halaman(route('pembukuan.rekapRealisasi', ['periode' => 'bulanan', 'bulan' => '2026-03']))
            ->assertSee('BULAN MARET 2026')
            ->assertSee('Realisasi Bulan Ini')
            ->assertSee('TOTAL BULAN')
            ->assertSee('Rp 1.000.000');

        $this->halaman(route('pembukuan.rekapRealisasi', ['periode' => 'semester', 'semester' => 1]))
            ->assertSee('SEMESTER 1 (JANUARI–JUNI) TAHUN 2026')
            ->assertSee('Realisasi Semester')
            ->assertSee('TOTAL SEMESTER')
            ->assertSee('Rp 3.000.000');

        $this->halaman(route('pembukuan.rekapRealisasi', ['periode' => 'tahunan']))
            ->assertSee('TAHUN ANGGARAN 2026')
            ->assertSee('Realisasi Tahun Ini')
            ->assertSee('TOTAL TAHUN')
            ->assertSee('Rp 4.500.000');

        $this->halaman(route('pembukuan.rekapRealisasi', ['periode' => 'tahapan', 'tahap' => $this->tahap2->id]))
            ->assertSee('TAHAPAN TAHAP 2 TAHUN 2026')
            ->assertSee('Realisasi Tahapan')
            ->assertSee('TOTAL TAHAPAN')
            ->assertSee('Rp 2.500.000');
    }

    public function test_sptjm_bulanan_menghitung_saldo_dan_mempertahankan_semester(): void
    {
        $this->halaman(route('pembukuan.sptjm', ['periode' => 'bulanan', 'bulan' => '2026-02']))
            ->assertSee('Bulan Februari Tahun Anggaran 2026')
            ->assertSee('Bulan Februari')
            ->assertSee('421/001/SPTJM-BOSP/2026/02')
            ->assertSee('Rp 13.000.000');

        $this->halaman(route('pembukuan.sptjm', ['semester' => 1]))
            ->assertSee('Semester 1 (Januari s.d. Juni) Tahun Anggaran 2026')
            ->assertSee('Rp 12.000.000');
    }

    public function test_realisasi_penerimaan_belanja_mendukung_semester(): void
    {
        $this->halaman(route('laporan-arkas.penerimaan-belanja', [
            'periode' => 'semester', 'semester' => 2,
        ]))
            ->assertSee('Semester 2 (Juli–Desember) 2026')
            ->assertSee('Realisasi s/d Semester Sebelumnya')
            ->assertSee('Realisasi Semester Ini')
            ->assertSee('Realisasi s/d Semester Ini')
            ->assertSee('Rp 3.000.000')  // realisasi semester 1
            ->assertSee('Rp 1.500.000')  // realisasi semester 2
            ->assertSee('Rp 4.500.000'); // s.d. semester 2

        $this->halaman(route('laporan-arkas.penerimaan-belanja', ['bulan' => 2]))
            ->assertSee('Februari 2026')
            ->assertSee('Realisasi Bulan Ini')
            ->assertSee('Rp 2.000.000');
    }
}
