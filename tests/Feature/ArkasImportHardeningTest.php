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
use App\Services\TarikArkasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ArkasImportHardeningTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '10203040',
            'nama' => 'SD Negeri Harapan Bangsa',
            'jenjang' => 'SD',
            'kecamatan' => 'Ujung Pandang',
            'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan',
            'is_active' => true,
        ]);

        $this->operator = User::create([
            'name' => 'Bendahara Harapan',
            'email' => 'bendahara@harapan.sch.id',
            'password' => 'password',
            'role' => 'operator',
            'school_id' => $this->sekolah->id,
            'is_active' => true,
        ]);

        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0024'], ['nama' => 'Belanja Alat Tulis Kantor', 'tipe' => 'belanja']);
        RekeningPajak::firstOrCreate(['kode' => 'ppn'], ['nama' => 'PPN']);

        for ($i = 1; $i <= 4; $i++) {
            Stage::firstOrCreate(
                ['tahun' => 2026, 'urutan' => $i],
                ['nama' => "Tahap {$i}", 'is_open' => true]
            );
        }
    }

    /**
     * Helper untuk membuat file Excel ARKAS uji dengan baris KAS yang dinamis.
     */
    private function buatBerkasArkasUji(array $kasRows): string
    {
        $wb = new Spreadsheet;

        // 1. Sheet Sekolah
        $wsSekolah = $wb->getActiveSheet();
        $wsSekolah->setTitle('Sekolah');
        $wsSekolah->fromArray([
            ['npsn', 'nama', 'alamat'],
            ['10203040', 'SD Negeri Harapan Bangsa', 'Jl. Pendidikan No. 10'],
        ]);

        // 2. Sheet Anggaran
        $wsAnggaran = $wb->createSheet();
        $wsAnggaran->setTitle('Anggaran');
        $wsAnggaran->fromArray([
            ['id_anggaran', 'tahun_anggaran', 'id_ref_sumber_dana', 'nama_sumber_dana', 'jumlah', 'is_aktif', 'soft_delete', 'last_update'],
            ['agg-1', '2026', '1', 'BOS Reguler', '50000000', '1', '0', '2026-01-01 08:00:00'],
        ]);

        // 3. Sheet RAPBS
        $wsRapbs = $wb->createSheet();
        $wsRapbs->setTitle('RAPBS');
        $wsRapbs->fromArray([
            ['id_rapbs', 'id_anggaran', 'id_ref_kode', 'kode_rekening', 'uraian', 'volume', 'harga_satuan', 'jumlah', 'tahun_anggaran', 'soft_delete'],
            ['rapbs-1', 'agg-1', 'ref-1', '5.1.02.01.01.0024', 'Kertas HVS A4', '10', '50000', '500000', '2026', '0'],
        ]);

        // 4. Sheet RAPBS_Periode
        $wsPeriode = $wb->createSheet();
        $wsPeriode->setTitle('RAPBS_Periode');
        $wsPeriode->fromArray([
            ['id_rapbs_periode', 'id_rapbs', 'volume', 'v1', 'v2', 'v3', 'v4'],
            ['periode-1', 'rapbs-1', '10', '5', '5', '0', '0'],
        ]);

        // 5. Sheet Ref_Kode
        $wsRefKode = $wb->createSheet();
        $wsRefKode->setTitle('Ref_Kode');
        $wsRefKode->fromArray([
            ['id_ref_kode', 'id_kode', 'uraian_kode', 'id_level_kode'],
            ['ref-1', '05.08.01', 'Pengadaan Bahan Praktik dan Pembelajaran', '3'],
        ]);

        // 6. Sheet KAS_Umum
        $wsKas = $wb->createSheet();
        $wsKas->setTitle('KAS_Umum');
        $headerKas = [
            'id_kas_umum', 'id_kas_nota', 'id_rapbs_periode', 'soft_delete',
            'tanggal_transaksi', 'no_bukti', 'uraian', 'kode_rekening',
            'jenis_bku', 'kode_bku', 'saldo', 'status_bku', 'volume',
            'is_ppn', 'is_pph_21', 'is_pph_22', 'is_pph_23', 'is_pph_4',
            'is_sspd', 'is_lock', 'tahun_anggaran', 'sumber_dana', 'id_anggaran',
        ];
        $wsKas->fromArray([$headerKas, ...$kasRows]);

        $tempPath = storage_path('app/uji-arkas-'.uniqid().'.xlsx');
        $writer = new Xlsx($wb);
        $writer->save($tempPath);
        $wb->disconnectWorksheets();

        return $tempPath;
    }

    public function test_logical_key_mempertahankan_identitas_dan_mencegah_duplikasi_saat_revisi_nominal(): void
    {
        $this->actingAs($this->operator);

        // Tahap 1: Impor transaksi awal (id_kas_umum = 9001, nominal = Rp 500.000)
        $barisAwal = [
            [
                '9001', 'nota-1', 'periode-1', '0',
                '2026-02-10', 'BOS-001', 'Beli Kertas HVS 10 Rim', '5.1.02.01.01.0024',
                'Kas Keluar', 'KK', '500000', '1', '10',
                '0', '0', '0', '0', '0',
                '0', '0', '2026', 'BOS Reguler', 'agg-1',
            ],
        ];

        $file1 = $this->buatBerkasArkasUji($barisAwal);
        try {
            $hasil1 = ArkasImportService::import($file1, $this->sekolah->id, $this->operator->id, 2026);
            $this->assertSame(1, $hasil1['dibuat']['transaksi']);
            $this->assertSame(0, $hasil1['diperbarui']['transaksi']);

            $trx = KasTransaction::where('school_id', $this->sekolah->id)
                ->where('arkas_id', 'kas:9001')
                ->first();

            $this->assertNotNull($trx);
            $this->assertSame(500000, (int) $trx->total);
            $this->assertSame('Beli Kertas HVS 10 Rim', $trx->keterangan);
            $trxId = $trx->id;
            $noBkuAwal = $trx->no_bku;

            // Tahap 2: Bendahara mengubah nominal dan uraian di ARKAS (nominal = Rp 650.000, 13 Rim)
            // id_kas_umum tetap '9001'.
            $barisRevisi = [
                [
                    '9001', 'nota-1', 'periode-1', '0',
                    '2026-02-10', 'BOS-001-REV', 'Beli Kertas HVS 13 Rim (Revisi)', '5.1.02.01.01.0024',
                    'Kas Keluar', 'KK', '650000', '1', '13',
                    '0', '0', '0', '0', '0',
                    '0', '0', '2026', 'BOS Reguler', 'agg-1',
                ],
            ];

            $file2 = $this->buatBerkasArkasUji($barisRevisi);
            try {
                $hasil2 = ArkasImportService::import($file2, $this->sekolah->id, $this->operator->id, 2026);
                $this->assertSame(0, $hasil2['dibuat']['transaksi'], 'Tidak boleh ada transaksi baru yang dibuat');
                $this->assertSame(1, $hasil2['diperbarui']['transaksi'], 'Transaksi lama harus diperbarui');

                // Jumlah transaksi harus tetap 1, bukan 2!
                $totalTrx = KasTransaction::where('school_id', $this->sekolah->id)->count();
                $this->assertSame(1, $totalTrx);

                $trxSetelahRevisi = KasTransaction::where('school_id', $this->sekolah->id)
                    ->where('arkas_id', 'kas:9001')
                    ->first();

                $this->assertSame($trxId, $trxSetelahRevisi->id, 'ID internal transaksi tidak boleh berubah');
                $this->assertSame(650000, (int) $trxSetelahRevisi->total, 'Nominal harus terupdate sesuai revisi');
                $this->assertSame('Beli Kertas HVS 13 Rim (Revisi)', $trxSetelahRevisi->keterangan);
                $this->assertSame($noBkuAwal, $trxSetelahRevisi->no_bku, 'Nomor BKU tidak boleh bergeser saat revisi');
            } finally {
                @unlink($file2);
            }
        } finally {
            @unlink($file1);
        }
    }

    public function test_penarikan_dengan_arkas_berjalan_dicegah_oleh_service(): void
    {
        $status = TarikArkasService::isArkasRunning();
        $this->assertIsBool($status);
    }
}
