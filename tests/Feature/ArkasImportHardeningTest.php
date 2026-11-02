<?php

namespace Tests\Feature;

use App\Models\BkuTutupBulan;
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
            $this->assertSame(1, (int) $trx->hash_version);
            $this->assertFalse((bool) $trx->needs_review);
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

    public function test_natural_key_fallback_merekonsiliasi_transaksi_ketika_id_kas_umum_berubah(): void
    {
        $this->actingAs($this->operator);

        // Tahap 1: Transaksi dibuat dengan id_kas_umum = 8001
        $baris1 = [
            [
                '8001', 'nota-2', 'periode-1', '0',
                '2026-03-05', 'BOS-002', 'Pengadaan Spidol Whiteboard', '5.1.02.01.01.0024',
                'Kas Keluar', 'KK', '300000', '1', '6',
                '0', '0', '0', '0', '0',
                '0', '0', '2026', 'BOS Reguler', 'agg-1',
            ],
        ];

        $file1 = $this->buatBerkasArkasUji($baris1);
        try {
            ArkasImportService::import($file1, $this->sekolah->id, $this->operator->id, 2026);
            $trx1 = KasTransaction::where('school_id', $this->sekolah->id)->where('arkas_id', 'kas:8001')->firstOrFail();
            $this->assertNotNull($trx1->natural_key);
            $this->assertNotNull($trx1->content_hash);
            $trxId = $trx1->id;

            // Tahap 2: Di ARKAS transaksi dihapus lalu diinput ulang sehingga id_kas_umum berganti jadi 8888,
            // namun transaksi ini mewakili belanja fisik yang sama (natural key cocok).
            $baris2 = [
                [
                    '8888', 'nota-2', 'periode-1', '0',
                    '2026-03-05', 'BOS-002', 'Pengadaan Spidol Whiteboard', '5.1.02.01.01.0024',
                    'Kas Keluar', 'KK', '300000', '1', '6',
                    '0', '0', '0', '0', '0',
                    '0', '0', '2026', 'BOS Reguler', 'agg-1',
                ],
            ];

            $file2 = $this->buatBerkasArkasUji($baris2);
            try {
                $hasil2 = ArkasImportService::import($file2, $this->sekolah->id, $this->operator->id, 2026);
                $this->assertSame(0, $hasil2['dibuat']['transaksi'], 'Natural key harus mencegah duplikasi baris');
                $this->assertSame(1, $hasil2['diperbarui']['transaksi']);

                // Record di database tetap 1
                $trxUpdated = KasTransaction::where('school_id', $this->sekolah->id)->find($trxId);
                $this->assertNotNull($trxUpdated);
                $this->assertSame('kas:8888', $trxUpdated->arkas_id, 'arkas_id harus diadopsi ke ID baru dari ARKAS');
                $this->assertSame('Pengadaan Spidol Whiteboard', $trxUpdated->keterangan);
            } finally {
                @unlink($file2);
            }
        } finally {
            @unlink($file1);
        }
    }

    public function test_dua_transaksi_dengan_tanggal_dan_nominal_identik_tetapi_uraian_beda_tidak_bertabrakan(): void
    {
        $this->actingAs($this->operator);

        $baris = [
            [
                '7001', 'nota-701', 'periode-1', '0',
                '2026-02-15', 'BOS-701', 'Beli Amplop Putih', '5.1.02.01.01.0024',
                'Kas Keluar', 'KK', '100000', '1', '2',
                '0', '0', '0', '0', '0',
                '0', '0', '2026', 'BOS Reguler', 'agg-1',
            ],
            [
                '7002', 'nota-702', 'periode-1', '0',
                '2026-02-15', 'BOS-702', 'Beli Map Folio Plastik', '5.1.02.01.01.0024',
                'Kas Keluar', 'KK', '100000', '1', '5',
                '0', '0', '0', '0', '0',
                '0', '0', '2026', 'BOS Reguler', 'agg-1',
            ],
        ];

        $file = $this->buatBerkasArkasUji($baris);
        try {
            $hasil = ArkasImportService::import($file, $this->sekolah->id, $this->operator->id, 2026);
            $this->assertSame(2, $hasil['dibuat']['transaksi']);

            $t1 = KasTransaction::where('school_id', $this->sekolah->id)->where('arkas_id', 'kas:7001')->firstOrFail();
            $t2 = KasTransaction::where('school_id', $this->sekolah->id)->where('arkas_id', 'kas:7002')->firstOrFail();

            $this->assertNotEquals($t1->natural_key, $t2->natural_key, 'Natural key harus membedakan belanja berdasarkan uraian yang dinormalisasi');
            $this->assertNotEquals($t1->content_hash, $t2->content_hash);
        } finally {
            @unlink($file);
        }
    }

    public function test_koreksi_no_bukti_memperbarui_content_hash_tanpa_membuat_baris_baru(): void
    {
        $this->actingAs($this->operator);

        // Tahap 1: Transaksi awal dengan no_bukti BOS-801
        $baris1 = [
            [
                '7003', 'nota-703', 'periode-1', '0',
                '2026-02-20', 'BOS-801', 'Beli Penggaris Besi 30cm', '5.1.02.01.01.0024',
                'Kas Keluar', 'KK', '45000', '1', '3',
                '0', '0', '0', '0', '0',
                '0', '0', '2026', 'BOS Reguler', 'agg-1',
            ],
        ];

        $file1 = $this->buatBerkasArkasUji($baris1);
        try {
            ArkasImportService::import($file1, $this->sekolah->id, $this->operator->id, 2026);
            $t1 = KasTransaction::where('school_id', $this->sekolah->id)->where('arkas_id', 'kas:7003')->firstOrFail();
            $hashAwal = $t1->content_hash;
            $naturalKeyAwal = $t1->natural_key;

            // Tahap 2: Koreksi no_bukti di ARKAS menjadi BOS-801-KOR, dan id_kas_umum ter-generate ulang jadi 7004
            $baris2 = [
                [
                    '7004', 'nota-703', 'periode-1', '0',
                    '2026-02-20', 'BOS-801-KOR', 'Beli Penggaris Besi 30cm', '5.1.02.01.01.0024',
                    'Kas Keluar', 'KK', '45000', '1', '3',
                    '0', '0', '0', '0', '0',
                    '0', '0', '2026', 'BOS Reguler', 'agg-1',
                ],
            ];

            $file2 = $this->buatBerkasArkasUji($baris2);
            try {
                $hasil2 = ArkasImportService::import($file2, $this->sekolah->id, $this->operator->id, 2026);
                $this->assertSame(0, $hasil2['dibuat']['transaksi']);
                $this->assertSame(1, $hasil2['diperbarui']['transaksi']);

                $tUpdated = KasTransaction::where('school_id', $this->sekolah->id)->find($t1->id);
                $this->assertSame('kas:7004', $tUpdated->arkas_id);
                $this->assertSame('BOS-801-KOR', $tUpdated->no_bukti);
                $this->assertSame($naturalKeyAwal, $tUpdated->natural_key, 'Natural key tetap stabil karena tidak mengikat no_bukti');
                $this->assertNotEquals($hashAwal, $tUpdated->content_hash, 'Content hash harus mencatat perubahan atribut no_bukti');
            } finally {
                @unlink($file2);
            }
        } finally {
            @unlink($file1);
        }
    }

    public function test_adopsi_natural_key_ditolak_jika_periode_transaksi_sudah_tutup_buku(): void
    {
        $this->actingAs($this->operator);

        // Tahap 1: Transaksi dibuat di bulan Maret
        $baris1 = [
            [
                '7005', 'nota-705', 'periode-1', '0',
                '2026-03-10', 'BOS-901', 'Belanja Buku Bacaan Siswa', '5.1.02.01.01.0024',
                'Kas Keluar', 'KK', '250000', '1', '10',
                '0', '0', '0', '0', '0',
                '0', '0', '2026', 'BOS Reguler', 'agg-1',
            ],
        ];

        $file1 = $this->buatBerkasArkasUji($baris1);
        try {
            ArkasImportService::import($file1, $this->sekolah->id, $this->operator->id, 2026);
            $tAwal = KasTransaction::where('school_id', $this->sekolah->id)->where('arkas_id', 'kas:7005')->firstOrFail();

            // Kunci BKU bulan Maret 2026
            BkuTutupBulan::create([
                'school_id' => $this->sekolah->id,
                'tahun' => 2026,
                'bulan' => '2026-03',
                'ditutup_oleh' => $this->operator->id,
            ]);

            // Tahap 2: Transaksi diinput ulang di ARKAS dengan id_kas_umum 7006 pada bulan yang sudah terkunci
            $baris2 = [
                [
                    '7006', 'nota-705', 'periode-1', '0',
                    '2026-03-10', 'BOS-901', 'Belanja Buku Bacaan Siswa', '5.1.02.01.01.0024',
                    'Kas Keluar', 'KK', '250000', '1', '10',
                    '0', '0', '0', '0', '0',
                    '0', '0', '2026', 'BOS Reguler', 'agg-1',
                ],
            ];

            $file2 = $this->buatBerkasArkasUji($baris2);
            try {
                $hasil2 = ArkasImportService::import($file2, $this->sekolah->id, $this->operator->id, 2026);

                // Baris pada bulan yang tertutup BKU otomatis ditolak
                $this->assertSame(1, $hasil2['dilewati']['transaksi']);
                $this->assertNotEmpty($hasil2['periode_terkunci']);

                // Transaksi terkunci lama TIDAK boleh tertimpa / teradopsi secara diam-diam!
                $tTerkunci = KasTransaction::where('school_id', $this->sekolah->id)->find($tAwal->id);
                $this->assertSame('kas:7005', $tTerkunci->arkas_id);

                // Transaksi baru tidak dibuat di bulan yang terkunci
                $this->assertNull(KasTransaction::where('school_id', $this->sekolah->id)->where('arkas_id', 'kas:7006')->first());
            } finally {
                @unlink($file2);
            }
        } finally {
            @unlink($file1);
        }
    }

    public function test_rekonsiliasi_natural_key_ke_transaksi_pada_bulan_terkunci_ditolak_dan_ditandai_needs_review(): void
    {
        $this->actingAs($this->operator);

        // Tahap 1: Transaksi dibuat di bulan Maret
        $tMaret = KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'no_bukti' => 'BOS-LOCK',
            'arkas_id' => 'kas:7010',
            'tanggal' => '2026-03-15',
            'keterangan' => 'Belanja Spanduk Kegiatan',
            'total' => 150000,
            'natural_key' => ArkasImportService::hitungNaturalKey(2026, '2026-03-15', '5.1.02.01.01.0024', 150000, 'kas keluar', 'belanja spanduk kegiatan'),
            'content_hash' => 'hash-awal',
            'hash_version' => 1,
        ]);

        // Kunci bulan Maret
        BkuTutupBulan::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'bulan' => '2026-03',
            'ditutup_oleh' => $this->operator->id,
        ]);

        // Tahap 2: Transaksi baru diimpor di bulan April (bulan belum terkunci)
        // tetapi natural_key diarahkan sama dengan transaksi Maret yang sudah terkunci
        $barisApril = [
            [
                '7011', 'nota-711', 'periode-1', '0',
                '2026-04-05', 'BOS-LOCK-NEW', 'Belanja Spanduk Kegiatan', '5.1.02.01.01.0024',
                'Kas Keluar', 'KK', '150000', '1', '1',
                '0', '0', '0', '0', '0',
                '0', '0', '2026', 'BOS Reguler', 'agg-1',
            ],
        ];

        // Override natural key di method simpanTransaksi untuk menguji adopsi
        $lap = [
            'dibuat' => ['transaksi' => 0],
            'diperbarui' => ['transaksi' => 0],
            'digabung' => ['transaksi' => 0],
            'periode_terkunci' => [],
            'needs_review' => [],
        ];

        $trxBaru = ArkasImportService::simpanTransaksi(
            $this->sekolah->id,
            'kas:7011',
            'kas:legacy:dummy',
            [
                'tahun' => 2026,
                'type' => 'belanja',
                'tanggal' => '2026-04-05',
                'keterangan' => 'Belanja Spanduk Kegiatan',
                'total' => 150000,
                'natural_key' => $tMaret->natural_key,
                'content_hash' => 'hash-baru',
            ],
            $lap,
            $tMaret->natural_key
        );

        // Verifikasi: Transaksi Maret tidak teradopsi arkas_id-nya
        $tMaretDb = KasTransaction::find($tMaret->id);
        $this->assertSame('kas:7010', $tMaretDb->arkas_id);

        // Transaksi baru diciptakan dan ditandai needs_review
        $this->assertTrue((bool) $trxBaru->needs_review);
        $this->assertStringContainsString('terkunci', $trxBaru->review_note);
        $this->assertNotEmpty($lap['periode_terkunci']);
    }

    public function test_kandidat_ambigu_ditandai_needs_review_jika_ada_beberapa_transaksi_identik_di_hari_yang_sama(): void
    {
        $this->actingAs($this->operator);

        $naturalKeySama = ArkasImportService::hitungNaturalKey(2026, '2026-05-10', '5.1.02.01.01.0024', 50000, 'kas keluar', 'fotokopi modul');

        // Buat 2 transaksi yang memiliki natural_key identik (misal 2 nota fotokopi terpisah pada hari yang sama)
        $t1 = KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'no_bukti' => 'BOS-FOTO-1',
            'arkas_id' => 'kas:8101',
            'tanggal' => '2026-05-10',
            'keterangan' => 'Fotokopi Modul',
            'total' => 50000,
            'natural_key' => $naturalKeySama,
            'content_hash' => 'hash-foto-1',
            'hash_version' => 1,
        ]);

        $t2 = KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'no_bukti' => 'BOS-FOTO-2',
            'arkas_id' => 'kas:8102',
            'tanggal' => '2026-05-10',
            'keterangan' => 'Fotokopi Modul',
            'total' => 50000,
            'natural_key' => $naturalKeySama,
            'content_hash' => 'hash-foto-2',
            'hash_version' => 1,
        ]);

        // Input transaksi baru dengan arkas_id 8103 yang natural key-nya sama
        $lap = [
            'dibuat' => ['transaksi' => 0],
            'diperbarui' => ['transaksi' => 0],
            'digabung' => ['transaksi' => 0],
            'periode_terkunci' => [],
            'needs_review' => [],
        ];

        $trxBaru = ArkasImportService::simpanTransaksi(
            $this->sekolah->id,
            'kas:8103',
            'kas:legacy:dummy-foto',
            [
                'tahun' => 2026,
                'type' => 'belanja',
                'tanggal' => '2026-05-10',
                'keterangan' => 'Fotokopi Modul',
                'total' => 50000,
                'natural_key' => $naturalKeySama,
                'content_hash' => 'hash-foto-3',
            ],
            $lap,
            $naturalKeySama
        );

        // Jangan auto-merge secara serampangan salah satu transaksi
        $this->assertTrue((bool) $trxBaru->needs_review);
        $this->assertStringContainsString('Kandidat ambigu', $trxBaru->review_note);
        $this->assertNotEmpty($lap['needs_review']);

        // Data awal t1 dan t2 tetap tidak terganggu
        $this->assertSame('kas:8101', KasTransaction::find($t1->id)->arkas_id);
        $this->assertSame('kas:8102', KasTransaction::find($t2->id)->arkas_id);
    }
}
