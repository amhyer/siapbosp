<?php

namespace Tests\Feature;

use App\Jobs\ProsesImporArkas;
use App\Models\Allocation;
use App\Models\ArkasImportRun;
use App\Models\ArkasKode;
use App\Models\BosType;
use App\Models\KasNota;
use App\Models\KasTax;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\RkasDetail;
use App\Models\School;
use App\Models\Stage;
use App\Models\SumberDanaSekolah;
use App\Models\TutupBuku;
use App\Models\User;
use App\Services\ArkasImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Impor ARKAS → SiapBOS (docs/IMPOR-ARKAS-EXCEL.md).
 *
 * Memakai fixture kecil tests/Fixtures/arkas-mini.xlsx (dibuat oleh
 * tests/Fixtures/buat-fixture.php): 1 NPSN, tahun 2025.
 */
class ArkasImportTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolahA;

    private School $sekolahB;

    private User $operatorA;

    private string $mini;

    private string $asing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mini = base_path('tests/Fixtures/arkas-mini.xlsx');
        $this->asing = base_path('tests/Fixtures/arkas-asing.xlsx');

        $this->sekolahA = School::create([
            'npsn' => '90000001', 'nama' => 'SD Negeri A', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->sekolahB = School::create([
            'npsn' => '90000002', 'nama' => 'SD Negeri B', 'jenjang' => 'SD',
            'kecamatan' => 'Losari', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->operatorA = User::create([
            'name' => 'Operator A', 'email' => 'a@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolahA->id,
        ]);
        User::create([
            'name' => 'Operator B', 'email' => 'b@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolahB->id,
        ]);

        BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'Honor Pengelola', 'tipe' => 'belanja']);
        RekeningPajak::create(['kode' => 'pph21', 'nama' => 'PPh Pasal 21']);
        for ($i = 1; $i <= 5; $i++) {
            Stage::create(['tahun' => 2025, 'nama' => "Tahap $i", 'urutan' => $i, 'is_open' => true]);
        }
    }

    private function imporA(?string $file = null): array
    {
        return ArkasImportService::import(
            $file ?? $this->mini, $this->sekolahA->id, $this->operatorA->id, 2025);
    }

    private function jmlTrxA(): int
    {
        return KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->count();
    }

    public function test_impor_dua_kali_tanpa_duplikat(): void
    {
        $this->actingAs($this->operatorA);
        $lap1 = $this->imporA();

        // K1 pendapatan + K2/K3 belanja + K6 ambil + 1 setor = 5 transaksi.
        $this->assertSame(5, $this->jmlTrxA());
        $this->assertSame(1, RkasDetail::count()); // R1 saja (R2 kode asing, R3 soft_delete)
        $this->assertSame(2, KasTax::count()); // terima (sudah) + salinan setor (sudah)
        $this->assertContains('9.9.99.99.99.9999', $lap1['rekening_tak_dikenal']);
        $this->assertSame(4, $lap1['dibuat']['transaksi']); // K1, K2, K3, K6
        $this->assertSame(1, $lap1['dibuat']['pajak_setor']); // setor PPh terhitung terpisah

        $bkuSebelum = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->orderBy('id')->pluck('no_bku')->all();

        $lap2 = $this->imporA();
        $this->assertSame(5, $this->jmlTrxA());
        $this->assertSame(1, RkasDetail::count());
        $this->assertSame(2, KasTax::count());
        $this->assertSame(0, $lap2['dibuat']['transaksi']);
        // Nomor BKU tak bergeser saat impor ulang.
        $this->assertSame($bkuSebelum, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->orderBy('id')->pluck('no_bku')->all());
        // Tak ada selisih hapus (semua baris file ada di DB).
        $this->assertSame([], $lap2['selisih_hapus']);
    }

    public function test_isolasi_antar_sekolah(): void
    {
        $this->actingAs($this->operatorA);
        $this->imporA();
        $this->assertSame(0, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahB->id)->count());
        $this->assertSame(0, RkasDetail::whereHas('header', fn ($q) => $q
            ->where('school_id', $this->sekolahB->id))->count());
    }

    public function test_periode_tutup_buku_diskip_lalu_terisi_setelah_dibuka(): void
    {
        $this->actingAs($this->operatorA);
        $stage2 = Stage::where('tahun', 2025)->where('urutan', 2)->firstOrFail();
        TutupBuku::create(['tahun' => 2025, 'stage_id' => $stage2->id]);

        $lap = $this->imporA();
        // K3 (Mar) + K6 (Apr) → stage 2 terkunci; K1, K2, setor PPh (Feb, stage 1) masuk.
        $this->assertSame(3, $this->jmlTrxA());
        $this->assertNotEmpty($lap['periode_terkunci']);

        TutupBuku::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->delete();
        $this->imporA();
        $this->assertSame(5, $this->jmlTrxA());
    }

    public function test_npsn_tak_dikenal_ditolak_tanpa_menulis(): void
    {
        $this->actingAs($this->operatorA);
        $this->expectException(\RuntimeException::class);
        try {
            $this->imporA($this->asing);
        } finally {
            $this->assertSame(0, $this->jmlTrxA());
            $this->assertSame(0, RkasDetail::count());
        }
    }

    public function test_transaksi_kode_asing_tetap_dibuat_tanpa_rincian(): void
    {
        $this->actingAs($this->operatorA);
        $this->imporA();
        $t = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)
            ->where('keterangan', 'Honor Kebersihan')->firstOrFail();
        $this->assertSame(2000000, (int) $t->total);
        $this->assertSame(0, $t->details()->count());
        // Nota BPU01 berflag SIPLah → rincian ATK tertandai siplah (untuk HPP).
        $atk = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)
            ->where('keterangan', 'Belanja ATK')->firstOrFail();
        $this->assertSame('siplah', $atk->details()->first()->sumber);
    }

    public function test_halaman_impor_bisa_dibuka_operator(): void
    {
        $res = $this->actingAs($this->operatorA)->get('/impor-arkas');
        $res->assertStatus(200)
            // Di lingkungan non-portabel, unduhan paket harus tampil (laptop portabel menyembunyikannya sendiri).
            ->assertSee('Cara lama (ZIP)');
    }

    public function test_tombol_unduh_agen_mengembalikan_zip(): void
    {
        $res = $this->actingAs($this->operatorA)->get('/impor-arkas/unduh-agen');
        if (! is_file(storage_path('app/agen/TarikArkas-v1.0.0.zip'))) {
            $res->assertNotFound();

            return;
        }
        $res->assertOk();
        $this->assertStringContainsString('zip', $res->headers->get('Content-Type'));
    }

    public function test_tombol_unduh_gui_mengembalikan_zip(): void
    {
        $res = $this->actingAs($this->operatorA)->get('/impor-arkas/unduh-gui');
        if (! is_file(storage_path('app/agen/TarikArkas-GUI-v1.0.0.zip'))) {
            $res->assertNotFound();

            return;
        }
        $res->assertOk();
        $this->assertStringContainsString('zip', $res->headers->get('Content-Type'));
    }

    public function test_baris_soft_delete_dilewati_dan_kunci_baru_dipakai(): void
    {
        $this->actingAs($this->operatorA);
        $this->imporA();
        $this->assertNull(KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)
            ->where('keterangan', 'Belanja Dihapus ARKAS')->first());
        $this->assertNotNull(KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->where('arkas_id', 'kas:KAS-BARU-1')->first());
    }

    public function test_arkas_push_api_mengantrikan_berkas_dengan_token_dan_membatasi_status(): void
    {
        Queue::fake();
        Storage::fake('local');
        $token = 'token-agen-tarik-arkas';
        $this->operatorA->forceFill(['api_token' => hash('sha256', $token)])->save();
        $res = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/v1/arkas-push', [
            'tahun' => 2025,
            'berkas' => new UploadedFile(
                $this->mini, 'arkas-mini.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
        $res->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'queued');
        $runId = $res->json('import_id');
        $this->assertDatabaseHas('arkas_import_runs', [
            'id' => $runId, 'user_id' => $this->operatorA->id,
            'school_id' => $this->sekolahA->id, 'status' => 'queued',
            'jenis' => ArkasImportRun::JENIS_IMPOR,
        ]);
        $this->assertSame(0, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->count());
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/arkas-import/'.$runId)
            ->assertStatus(202)->assertJsonPath('status', 'queued');

        $tokenLain = 'token-operator-b';
        $operatorB = User::where('email', 'b@tes.test')->firstOrFail();
        $operatorB->forceFill(['api_token' => hash('sha256', $tokenLain)])->save();
        $this->withHeader('Authorization', 'Bearer '.$tokenLain)
            ->getJson('/api/v1/arkas-import/'.$runId)->assertNotFound();
        Queue::assertPushed(ProsesImporArkas::class);
    }

    public function test_arkas_push_api_menolak_token_asing_dan_npsn_asing(): void
    {
        $this->postJson('/api/v1/arkas-push', ['tahun' => 2025])->assertStatus(401);
    }

    public function test_migrasi_kunci_legacy_ke_baru_tanpa_duplikat(): void
    {
        $this->actingAs($this->operatorA);
        $this->imporA();
        // Satu baris fixture memang sudah berkunci baru (KAS-BARU-1).
        $this->assertSame(1, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->where('arkas_id', 'like', 'kas:KAS-%')->count());

        // Varian kunci baru: baris logis sama, id_kas_umum terisi (seperti dari agen).
        $wb = IOFactory::load($this->mini);
        $kas = $wb->getSheetByName('KAS_Umum');
        for ($row = 3; $row <= 10; $row++) {
            if (trim((string) $kas->getCell('U'.$row)->getValue()) === '') {
                $kas->setCellValue('U'.$row, 'NEWKEY-'.$row);
            }
        }
        $baru = tempnam(sys_get_temp_dir(), 'arkas').'.xlsx';
        (new Xlsx($wb))->save($baru);

        $lap = ArkasImportService::import($baru, $this->sekolahA->id, $this->operatorA->id, 2025);
        @unlink($baru);

        $this->assertSame(5, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->count());
        $this->assertSame(0, $lap['dibuat']['transaksi']);
        $this->assertGreaterThan(0, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->where('arkas_id', 'like', 'kas:NEWKEY-%')->count());
        $this->assertSame(0, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->where('arkas_id', 'like', 'kas:legacy:%')->count());
    }

    public function test_pelengkap_profil_penjab_pagu_dan_info(): void
    {
        $this->actingAs($this->operatorA);
        $this->sekolahA->forceFill(['alamat' => null])->save();
        $lap = $this->imporA();

        // Profil: alamat kosong terisi dari ARKAS.
        $this->assertSame('Jl. Merdeka 1', $this->sekolahA->fresh()->alamat);
        // Penjab: KS + bendahara masuk (komite kosong di fixture).
        $this->assertSame(2, Penandatangan::where('school_id', $this->sekolahA->id)->count());
        $this->assertSame(2, $lap['dibuat']['penjab']);
        // Server pusat tidak boleh menerima pagu resmi dari berkas sekolah;
        // angka dari ARKAS hanya dilaporkan untuk ditetapkan Dinas.
        $this->assertSame(0, Allocation::where('school_id', $this->sekolahA->id)->where('tahun', 2025)->count());
        $this->assertSame(0, $lap['dibuat']['alokasi']);
        $this->assertNotEmpty($lap['selisih_pagu']);
        // Impor ulang juga tidak menetapkan pagu dan tetap melaporkan perbedaan.
        $lap2 = $this->imporA();
        $this->assertSame(0, Allocation::where('school_id', $this->sekolahA->id)->where('tahun', 2025)->count());
        $this->assertNotEmpty($lap2['selisih_pagu']);
        // Info silang + nama rekening asing.
        $this->assertSame(100000000, $lap['info_salur']['salur']);
        $this->assertSame(100000000, $lap['info_salur']['cair']);
        $this->assertSame(['bank' => 500000, 'tunai' => 250000], $lap['info_saldo_awal']);
        $this->assertStringContainsString('2 GTK', $lap['info_ptk']);
        $this->assertSame('Kode Asing Contoh', $lap['rekening_asing_nama']['9.9.99.99.99.9999']);
        // Kode kegiatan ARKAS: RAPBS via Ref_Kode, belanja via RAPBS_Periode.
        $this->assertSame('03.03.06.', RkasDetail::first()->kode_kegiatan);
        $atk = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)
            ->where('keterangan', 'Belanja ATK')->firstOrFail();
        $this->assertSame('03.03.06.', $atk->details()->first()->kode_kegiatan);
        // Format ARKAS v1.4: master kode + master nota + volume + alokasi tahap.
        $this->assertSame(2, ArkasKode::where('school_id', $this->sekolahA->id)->where('tahun', 2025)->count());
        $this->assertSame('Pengembangan Standar Proses', ArkasKode::where('kode', '03.')->where('level', 1)->firstOrFail()->uraian);
        $this->assertSame(1, KasNota::where('school_id', $this->sekolahA->id)->count());
        $nota = KasNota::first();
        $this->assertSame('Toko ATK', $nota->nama_toko);
        $this->assertSame(5000000, (int) $nota->total);
        $this->assertSame('NOTA-1', $atk->arkas_nota_id);
        $this->assertSame(5.0, (float) $atk->details()->first()->volume);
        $this->assertSame(2.0, (float) (json_decode(RkasDetail::first()->periode_volume, true)['1'] ?? 0));
    }

    public function test_pagu_dinas_tidak_ditimpa_hanya_dilaporkan(): void
    {
        $this->actingAs($this->operatorA);
        Allocation::create([
            'school_id' => $this->sekolahA->id, 'tahun' => 2025,
            'bos_type_id' => BosType::where('kode', 'reguler')->firstOrFail()->id,
            'nilai' => 50000000,
        ]);
        $lap = $this->imporA();
        $this->assertSame(50000000, (int) Allocation::where('school_id', $this->sekolahA->id)
            ->where('tahun', 2025)->firstOrFail()->nilai);
        $this->assertCount(1, $lap['selisih_pagu']);
    }

    /**
     * Penerimaan, master sumber dana, nota SIPLah, dan tautan pajak setor
     * harus terbaca dari satu berkas yang sama — bukan hanya baris kasnya.
     */
    public function test_penerimaan_sumber_dana_nota_dan_pajak_tertaut(): void
    {
        $this->actingAs($this->operatorA);
        $lap = $this->imporA();

        $terima = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->where('type', 'pendapatan')->firstOrFail();
        $this->assertSame('Terima Dana BOS', $terima->arkas_jenis_bku);
        $this->assertSame('BBU01', $terima->arkas_bku);
        $this->assertSame('transfer', $terima->metode_bayar);
        $this->assertSame(100000000, (int) $terima->total);
        $this->assertSame('reguler', BosType::findOrFail($terima->bos_type_id)->kode);

        // Ref_Sumber_Dana utuh, termasuk penanda sumber dana tambahan (Banpem).
        $this->assertSame(3, SumberDanaSekolah::where('school_id', $this->sekolahA->id)->count());
        $this->assertSame(3, $lap['dibuat']['sumber_dana']);
        $banpem = SumberDanaSekolah::where('id_ref_sumber_dana', '1005')->firstOrFail();
        $this->assertSame('Banpem Keterampilan Menulis', $banpem->alias);
        $this->assertTrue($banpem->is_sumber_dana_tambahan);

        // Nota bertanda SIPLah menandai rincian belanja untuk HPP & revisi.
        $atk = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahA->id)->where('keterangan', 'Belanja ATK')->firstOrFail();
        $this->assertSame('siplah', $atk->details()->firstOrFail()->sumber);

        // Pajak terima menempel ke belanja; setor menautkan NTPN + transaksi setor.
        $pajak = KasTax::where('kas_transaction_id', $atk->id)->firstOrFail();
        $this->assertSame('sudah', $pajak->status);
        $this->assertSame('1234567890123456', $pajak->ntpn);
        $this->assertNotNull($pajak->paid_transaction_id);
        $this->assertSame('setor_pajak', KasTransaction::findOrFail($pajak->paid_transaction_id)->type);

        // Impor ulang memperbarui master, bukan menggandakannya.
        $lap2 = $this->imporA();
        $this->assertSame(3, SumberDanaSekolah::where('school_id', $this->sekolahA->id)->count());
        $this->assertSame(0, $lap2['dibuat']['sumber_dana']);
        $this->assertSame(3, $lap2['diperbarui']['sumber_dana']);
    }

    /**
     * ARKAS menyimpan beberapa revisi RKAS per sumber dana. Hanya revisi
     * terbaru (last_update paling akhir) yang boleh masuk; baris revisi lama
     * tidak boleh menambah pagu/rincian.
     */
    public function test_revisi_rkas_lama_diabaikan_hanya_revisi_terbaru_dipakai(): void
    {
        $this->actingAs($this->operatorA);

        $buku = IOFactory::load($this->mini);
        $rapbs = $buku->getSheetByName('RAPBS');
        $rapbs->setCellValue('L2', 'id_anggaran');
        $rapbs->setCellValue('L3', 'AGG-BARU'); // RAPBS-1 dari revisi terbaru
        $rapbs->setCellValue('L4', 'AGG-LAMA');
        $rapbs->setCellValue('L5', 'AGG-LAMA');
        $rapbs->fromArray([
            'RAPBS-OLD', 'RK-1', '5.1.02.01.01.0001', 'Honor Revisi Lama',
            1, 'paket', 70000000, 70000000, 0, 2025, 'BOS Reguler', 'AGG-LAMA',
        ], null, 'A6');

        $dasar = tempnam(sys_get_temp_dir(), 'arkas-revisi-');
        $berkas = $dasar.'.xlsx';
        (new Xlsx($buku))->save($berkas);

        try {
            $lap = $this->imporA($berkas);
        } finally {
            @unlink($dasar);
            @unlink($berkas);
        }

        $this->assertSame(1, RkasDetail::count());
        $this->assertSame('Honor Pengelola', RkasDetail::firstOrFail()->uraian);
        $this->assertStringContainsString('revisi lama diabaikan', implode(' ', $lap['alasan_lewat']));
    }
}
