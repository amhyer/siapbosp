<?php

namespace Tests\Unit;

use App\Models\KasTransaction;
use App\Models\School;
use App\Services\KasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian ledger kas SiapBOSP: efek kas per jenis transaksi,
 * saldo berjalan tunai/bank, SILPA antar tahun, dan nomor BKU otomatis.
 *
 * Memakai Tests\TestCase (bukan PHPUnit murni) karena butuh Eloquent & migrasi.
 */
class KasServiceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'npsn' => '90000099', 'nama' => 'SD Negeri Ledger Tes', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
    }

    private function trx(array $a): KasTransaction
    {
        return KasTransaction::create($a + [
            'school_id' => $this->school->id,
            'tahun' => 2026,
            'keterangan' => 'Transaksi tes',
        ]);
    }

    public function test_efek_kas_per_jenis_transaksi(): void
    {
        $this->assertSame([0, 100], KasService::efek('pendapatan', 'transfer', 100));
        $this->assertSame([-100, 0], KasService::efek('belanja', 'tunai', 100));
        $this->assertSame([0, -100], KasService::efek('belanja', 'transfer', 100));
        $this->assertSame([100, -100], KasService::efek('ambil_simpanan', 'tunai', 100));
        $this->assertSame([-100, 100], KasService::efek('mutasi_tunai_ke_bank', null, 100));
        $this->assertSame([-100, 100], KasService::efek('mutasi-tunai-ke-bank', null, 100));
        $this->assertSame([0, -100], KasService::efek('setor_pajak', null, 100));
        $this->assertSame([0, -100], KasService::efek('setor_utang_pajak', null, 100));
        $this->assertSame([0, 0], KasService::efek('jenis_tidak_dikenal', null, 100));
    }

    public function test_ledger_saldo_berjalan_urut_tanggal_dan_no_bku(): void
    {
        // Dibuat sengaja tidak berurutan untuk membuktikan pengurutan ledger.
        $belanja = $this->trx([
            'type' => 'belanja', 'no_bku' => 3, 'tanggal' => '2026-03-07',
            'metode_bayar' => 'tunai', 'total' => 3325000,
        ]);
        $pendapatan = $this->trx([
            'type' => 'pendapatan', 'no_bku' => 1, 'tanggal' => '2026-03-04',
            'metode_bayar' => 'transfer', 'total' => 60000000,
        ]);
        $ambil = $this->trx([
            'type' => 'ambil_simpanan', 'no_bku' => 2, 'tanggal' => '2026-03-06',
            'metode_bayar' => 'tunai', 'total' => 5000000,
        ]);

        $l = KasService::ledger($this->school->id);

        $this->assertSame(
            [$pendapatan->id, $ambil->id, $belanja->id],
            array_map(fn ($i) => $i['trx']->id, $l['items'])
        );
        $this->assertSame(60000000, $l['items'][0]['saldo']);
        $this->assertSame(60000000, $l['items'][1]['saldo']);
        $this->assertSame(56675000, $l['items'][2]['saldo']);

        // Tunai: 5.000.000 - 3.325.000; Bank: 60.000.000 - 5.000.000
        $this->assertSame(1675000, $l['tunai']);
        $this->assertSame(55000000, $l['bank']);
        $this->assertSame(56675000, $l['total']);
        $this->assertSame(0, $l['saldoAwalTahun']);
    }

    public function test_saldo_silpa_tahun_lalu_terbawa_ke_tahun_berikutnya(): void
    {
        // 2025: sisa 6.000.000 di bank.
        $this->trx(['tahun' => 2025, 'type' => 'pendapatan', 'no_bku' => 1, 'tanggal' => '2025-05-01', 'metode_bayar' => 'transfer', 'total' => 10000000]);
        $this->trx(['tahun' => 2025, 'type' => 'belanja', 'no_bku' => 2, 'tanggal' => '2025-06-01', 'metode_bayar' => 'transfer', 'total' => 4000000]);
        // 2026: dana cair 5.000.000.
        $t2026 = $this->trx(['tahun' => 2026, 'type' => 'pendapatan', 'no_bku' => 1, 'tanggal' => '2026-03-04', 'metode_bayar' => 'transfer', 'total' => 5000000]);

        $l = KasService::ledger($this->school->id, 2026);

        $this->assertSame(6000000, $l['saldoAwalTahun']);
        $this->assertSame([$t2026->id], array_map(fn ($i) => $i['trx']->id, $l['items']));
        // Tunai 0; bank 10.000.000 - 4.000.000 + 5.000.000 = 11.000.000 (termasuk SILPA)
        $this->assertSame(11000000, $l['total']);

        $saldo = KasService::saldo($this->school->id, 2026);
        $this->assertSame(0, $saldo['tunai']);
        $this->assertSame(11000000, $saldo['bank']);
        $this->assertSame(11000000, $saldo['total']);
    }

    public function test_nomor_bku_berikutnya_per_bulan(): void
    {
        // Belum ada transaksi → mulai dari 1.
        $this->assertSame(1, KasService::nextNoBku($this->school->id, '2026-03-15'));

        $this->trx(['type' => 'pendapatan', 'no_bku' => 1, 'tanggal' => '2026-03-04', 'metode_bayar' => 'transfer', 'total' => 1000000]);
        $this->trx(['type' => 'belanja', 'no_bku' => 2, 'tanggal' => '2026-03-07', 'metode_bayar' => 'tunai', 'total' => 100000]);

        $this->assertSame(3, KasService::nextNoBku($this->school->id, '2026-03-31'));

        // Bulan lain dihitung terpisah → kembali ke 1.
        $this->assertSame(1, KasService::nextNoBku($this->school->id, '2026-04-01'));
    }
}
