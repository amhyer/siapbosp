<?php

namespace Tests\Feature;

use App\Models\BkuTutupBulan;
use App\Models\KasDetail;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buku Kas Pembantu Bank — bentuknya dari bku-bank-output.pdf (ARKAS 4.3.1):
 *   "B U K U   K A S   P E M B A N T U   B A N K", "TAHUN : …", kolom
 *   TANGGAL / KODE KEGIATAN / KODE REKENING / NO. BUKTI / URAIAN /
 *   PENERIMAAN / PENGELUARAN / SALDO, baris pembuka
 *   "Saldo Bank Bulan Desember <tahun lalu>", baris Bunga Bank & Pajak Bunga
 *   tiap bulan tutup, baris Jumlah, dan kaki halaman
 *   "BKU Pembantu Bank Tahun … - NPSN : …".
 *
 * Dokumen ini HANYA memuat mutasi rekening bank — belanja tunai tidak boleh ikut.
 */
class BkuBankCetakTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['npsn' => '40308888', 'nama' => 'SDN Uji Bank', 'jenjang' => 'SD',
            'desa' => 'Jl. Bank No. 7', 'kecamatan' => 'Makasar', 'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan']);

        $this->actingAs(User::create(['name' => 'Bendahara Bank', 'email' => 'bank@sdn.test',
            'password' => 'secret123', 'role' => 'operator', 'school_id' => $this->school->id]));

        Penandatangan::create(['school_id' => $this->school->id, 'nama' => 'Nawawi Hamzah',
            'nip' => '197006202004111001', 'jabatan' => 'Kepala Sekolah']);
        Penandatangan::create(['school_id' => $this->school->id, 'nama' => 'Hairuddin',
            'nip' => '198705172020121003', 'jabatan' => 'Bendahara']);

        $rekHonor = Rekening::firstOrCreate(['kode' => '5.1.02.02.01.0013'], ['nama' => 'Honorarium Guru', 'tipe' => 'belanja']);
        $rekAtk = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0012'], ['nama' => 'Alat Tulis Kantor', 'tipe' => 'belanja']);

        // Pendapatan tahap 1 masuk bank, Januari 2026.
        KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'pendapatan',
            'no_bku' => 1, 'no_bukti' => 'BBU01', 'tanggal' => '2026-01-22', 'metode_bayar' => 'transfer',
            'keterangan' => 'Terima Dana BOSP Tahap 1 2026', 'total' => 100_000_000]);

        // Belanja lewat transfer → masuk buku bank.
        $transfer = KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'belanja',
            'no_bku' => 2, 'no_bukti' => 'BNU02', 'tanggal' => '2026-02-10', 'metode_bayar' => 'transfer',
            'keterangan' => 'Honor Guru Kelas', 'total' => 4_000_000]);
        KasDetail::create(['kas_transaction_id' => $transfer->id, 'rekening_id' => $rekHonor->id,
            'kode_kegiatan' => '07.12.01.', 'uraian' => 'Honor Guru Kelas', 'nilai' => 4_000_000]);

        // Belanja tunai → TIDAK boleh muncul di buku bank.
        $tunai = KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'belanja',
            'no_bku' => 3, 'no_bukti' => 'BPU03', 'tanggal' => '2026-02-11', 'metode_bayar' => 'tunai',
            'keterangan' => 'Beli ATK tunai', 'total' => 500_000]);
        KasDetail::create(['kas_transaction_id' => $tunai->id, 'rekening_id' => $rekAtk->id,
            'kode_kegiatan' => '06.05.08.', 'uraian' => 'ATK', 'nilai' => 500_000]);

        // Tarik tunai → pengeluaran bank.
        KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'ambil_simpanan',
            'no_bku' => 4, 'no_bukti' => 'BBU02', 'tanggal' => '2026-02-12', 'metode_bayar' => 'transfer',
            'keterangan' => 'Tarik Tunai', 'total' => 20_000_000]);

        // Januari ditutup tanpa bunga: ARKAS tetap mencetak baris Bunga/Pajak Bunga bernilai 0.
        BkuTutupBulan::create(['school_id' => $this->school->id, 'tahun' => 2026, 'bulan' => '2026-01',
            'ditutup_oleh' => 'Bendahara Bank', 'bunga_bank' => 0, 'pajak_bunga' => 0]);
    }

    private function cetak()
    {
        return $this->withSession(['tahun' => 2026])
            ->get(route('bku.bank'))
            ->assertOk();
    }

    /** Kop, penanda, dan delapan kolom ARKAS. */
    public function test_bku_bank_berkop_dan_berkolom_arkas(): void
    {
        $r = $this->cetak();

        $r->assertSee('B U K U   K A S   P E M B A N T U   B A N K')
            ->assertSee('BKU - BANK')
            ->assertSee('TAHUN : 2026')
            ->assertSee('NPSN : 40308888')
            ->assertSee('Nama Sekolah');

        foreach (['TANGGAL', 'KODE<br>KEGIATAN', 'KODE<br>REKENING', 'NO. BUKTI', 'URAIAN',
            'PENERIMAAN', 'PENGELUARAN', 'SALDO'] as $kolom) {
            $r->assertSee($kolom, false);
        }
        // Baris nomor 1–8 seperti BKU Kas Umum.
        $r->assertSee('bku-nomor', false);
    }

    /** Baris pembuka memakai tahun sebelum periode seperti ARKAS. */
    public function test_bku_bank_memulai_dengan_saldo_bank_desember_tahun_lalu(): void
    {
        $this->cetak()
            ->assertSee('01-01-2026')
            ->assertSee('Saldo Bank Bulan Desember 2025');
    }

    /** Hanya mutasi bank: pendapatan, transfer, tarik tunai; belanja tunai tidak. */
    public function test_bku_bank_hanya_memuat_mutasi_bank(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('Terima Dana BOSP Tahap 1 2026', $html);
        $this->assertStringContainsString('Honor Guru Kelas', $html);
        $this->assertStringContainsString('Tarik Tunai', $html);
        $this->assertStringNotContainsString('Beli ATK tunai', $html, 'Belanja tunai tidak boleh masuk buku bank.');

        // Kode rekening & kode kegiatan dari rincian transaksi.
        $this->assertStringContainsString('5.1.02.02.01.0013', $html);
        $this->assertStringContainsString('07.12.01.', $html);
        // No. bukti ARKAS dipakai apa adanya.
        $this->assertStringContainsString('BNU02', $html);
    }

    /** Saldo berjalan dihitung dari posisi bank, bukan saldo kas gabungan. */
    public function test_bku_bank_menghitung_saldo_bank_berjalan(): void
    {
        $html = $this->cetak()->getContent();

        // 100.000.000 → belanja transfer 4.000.000 → 96.000.000 → tarik tunai 20.000.000 → 76.000.000.
        $this->assertStringContainsString('<td class="num">100.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num">96.000.000</td>', $html);
        $this->assertStringContainsString('<td class="num">76.000.000</td>', $html);
        // Baris Jumlah: penerimaan 100.000.000, pengeluaran 4.000.000 + 20.000.000.
        $this->assertStringContainsString('>Jumlah</td>', $html);
        $this->assertStringContainsString('<td class="num">24.000.000</td>', $html);
    }

    /** Bulan yang ditutup tanpa bunga tetap mencetak baris Bunga Bank & Pajak Bunga. */
    public function test_bku_bank_mencetak_baris_bunga_bulanan(): void
    {
        $html = $this->cetak()->getContent();

        $this->assertStringContainsString('Bunga Bank', $html);
        $this->assertStringContainsString('Pajak Bunga', $html);
        // Baris bernilai 0 pada tanggal akhir bulan tutup (31 Januari 2026).
        $this->assertStringContainsString('31-01-2026', $html);
    }

    /** Transaksi bunga hasil Tutup BKU diberi nama pendek seperti ARKAS. */
    public function test_bku_bank_menormalkan_nama_bunga_dari_tutup_bku(): void
    {
        $rekBunga = Rekening::firstOrCreate(['kode' => '8.1.03.01.01.0001'],
            ['nama' => 'Pendapatan Jasa Giro / Bunga Bank BOSP', 'tipe' => 'pendapatan']);
        $bunga = KasTransaction::create(['school_id' => $this->school->id, 'tahun' => 2026, 'type' => 'pendapatan',
            'no_bku' => 9, 'tanggal' => '2026-03-31', 'metode_bayar' => 'transfer',
            'keterangan' => 'Bunga Bank / Jasa Giro Maret 2026', 'total' => 125_000]);
        KasDetail::create(['kas_transaction_id' => $bunga->id, 'rekening_id' => $rekBunga->id,
            'uraian' => 'Bunga Bank / Jasa Giro Maret 2026', 'nilai' => 125_000]);
        BkuTutupBulan::create(['school_id' => $this->school->id, 'tahun' => 2026, 'bulan' => '2026-03',
            'ditutup_oleh' => 'Bendahara Bank', 'bunga_bank' => 125_000, 'pajak_bunga' => 0]);

        $html = $this->cetak()->getContent();

        // Uraian panjang diganti "Bunga Bank", nilainya masuk kolom Penerimaan.
        $this->assertStringContainsString('<td class="num">125.000</td>', $html);
        $this->assertStringNotContainsString('Bunga Bank / Jasa Giro Maret 2026', $html);
    }

    /** Tanda tangan 2 kolom + kalimat penutupan khas lembar bank. */
    public function test_bku_bank_menandatangani_dan_menutup_buku(): void
    {
        $this->cetak()
            ->assertSee('Menyetujui,')
            ->assertSee('Kepala Sekolah')
            ->assertSee('Nawawi Hamzah')
            ->assertSee('NIP. 197006202004111001')
            ->assertSee('Bendahara')
            ->assertSee('Hairuddin')
            ->assertSee('NIP. 198705172020121003')
            ->assertSee('Buku Kas Umum Ditutup dengan keadaan/posisi buku sebagai berikut')
            ->assertSee('Saldo Bank :');
    }

    /** Kaki halaman menyebut tahun, bukan bulan — dan tanpa kolom tunai. */
    public function test_bku_bank_berkaki_halaman_tahunan(): void
    {
        $r = $this->cetak();

        $r->assertSee('BKU Pembantu Bank Tahun 2026 - NPSN : 40308888', false);
        $r->assertDontSee('Saldo Kas Tunai');
    }

    /** Tanpa transaksi lembar tetap terbuka (tidak 500). */
    public function test_bku_bank_tanpa_transaksi_tetap_terbuka(): void
    {
        KasDetail::query()->delete();
        KasTransaction::query()->delete();
        BkuTutupBulan::query()->delete();

        $this->cetak()
            ->assertSee('B U K U   K A S   P E M B A N T U   B A N K')
            ->assertSee('Belum ada mutasi rekening bank pada tahun 2026.');
    }
}
