<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\SyncSnapshot;
use App\Models\TutupBuku;
use App\Models\User;
use App\Services\LpjManifestService;
use App\Services\SyncSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualLayerAndManifestTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private User $dinas;

    private Stage $stage1;

    private Stage $stage2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '20102030',
            'nama' => 'SMP Negeri Unggulan 1',
            'jenjang' => 'SMP',
            'kecamatan' => 'Tamalanrea',
            'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan',
            'is_active' => true,
        ]);

        $this->operator = User::create([
            'name' => 'Bendahara SMP 1',
            'email' => 'bendahara@smp1.sch.id',
            'password' => 'password',
            'role' => 'operator',
            'school_id' => $this->sekolah->id,
            'is_active' => true,
        ]);

        $this->dinas = User::create([
            'name' => 'Verifikator Dinas Pendidikan',
            'email' => 'verifikator@disdik.makassar.go.id',
            'password' => 'password',
            'role' => 'dinas',
            'school_id' => null,
            'is_active' => true,
        ]);

        BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0024'], ['nama' => 'Belanja Alat Tulis Kantor', 'tipe' => 'belanja']);

        $this->stage1 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $this->stage2 = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 2', 'urutan' => 2, 'is_open' => true]);
    }

    public function test_rekam_snapshot_mencatat_lapis_1_dengan_manifest_hash_deterministik(): void
    {
        // 1. Buat transaksi pendapatan dan belanja
        KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'pendapatan',
            'tanggal' => '2026-02-01',
            'total' => 100000000,
            'keterangan' => 'Penerimaan BOS Reguler Tahap 1',
            'stage_id' => $this->stage1->id,
            'arkas_id' => 'kas:1001',
            'content_hash' => 'hash-pendapatan-1',
        ]);

        KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'tanggal' => '2026-02-15',
            'total' => 15000000,
            'keterangan' => 'Pengadaan ATK Ujian',
            'stage_id' => $this->stage1->id,
            'arkas_id' => 'kas:1002',
            'content_hash' => 'hash-belanja-1',
        ]);

        // 2. Rekam snapshot pada tanggal 2026-02-28
        $snapshot = SyncSnapshotService::rekamSnapshot($this->sekolah->id, 2026, '2026-02-28');

        $this->assertNotNull($snapshot);
        $this->assertSame(100000000, $snapshot->total_pendapatan);
        $this->assertSame(15000000, $snapshot->total_belanja);
        $this->assertSame(85000000, $snapshot->saldo);
        $this->assertSame(2, $snapshot->total_transaksi);
        $this->assertNotEmpty($snapshot->manifest_hash);
        $this->assertFalse($snapshot->is_frozen);

        // 3. Rekam ulang pada hari yang sama -> idempoten, memperbarui record yang sama
        $snapshotUlang = SyncSnapshotService::rekamSnapshot($this->sekolah->id, 2026, '2026-02-28');
        $this->assertSame($snapshot->id, $snapshotUlang->id);
        $this->assertSame(1, SyncSnapshot::where('school_id', $this->sekolah->id)->count());
    }

    public function test_lpj_manifest_menghasilkan_audit_chain_dengan_previous_manifest_hash(): void
    {
        // 1. Buat transaksi untuk Tahap 1
        KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'tanggal' => '2026-03-10',
            'total' => 25000000,
            'keterangan' => 'Belanja Kegiatan Tahap 1',
            'stage_id' => $this->stage1->id,
            'arkas_id' => 'kas:T1-01',
            'content_hash' => 'hash-t1-01',
        ]);

        // 2. Ajukan & Sahkan LPJ Tahap 1
        $lpj1 = LpjManifestService::ajukanLpj($this->sekolah->id, 2026, $this->stage1->id, $this->operator->id);
        $this->assertSame('GENESIS', $lpj1->previous_manifest_hash);
        $this->assertSame('dikirim', $lpj1->status);
        $this->assertNotNull($lpj1->school_signature);

        $lpj1Sah = LpjManifestService::sahkanLpj($lpj1, $this->dinas, 'LPJ Tahap 1 Disahkan');
        $this->assertSame('diterima', $lpj1Sah->status);
        $this->assertNotNull($lpj1Sah->dinas_signature);
        $hashTahap1 = $lpj1Sah->manifest_hash;

        // 3. Buat transaksi untuk Tahap 2
        KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'tanggal' => '2026-08-15',
            'total' => 30000000,
            'keterangan' => 'Belanja Kegiatan Tahap 2',
            'stage_id' => $this->stage2->id,
            'arkas_id' => 'kas:T2-01',
            'content_hash' => 'hash-t2-01',
        ]);

        // 4. Ajukan LPJ Tahap 2
        $lpj2 = LpjManifestService::ajukanLpj($this->sekolah->id, 2026, $this->stage2->id, $this->operator->id);

        // Verifikasi rantai audit: previous_manifest_hash Tahap 2 harus menautkan manifest_hash Tahap 1
        $this->assertSame($hashTahap1, $lpj2->previous_manifest_hash, 'Audit chain harus terhubung ke manifest tahap sebelumnya');
        $this->assertNotSame($lpj1Sah->manifest_hash, $lpj2->manifest_hash);
    }

    public function test_pengesahan_lpj_lapis_2_mengunci_dan_membekukan_snapshot_lapis_1(): void
    {
        // 1. Buat transaksi Tahap 1 dan snapshot Lapis 1
        KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'tanggal' => '2026-04-10',
            'total' => 5000000,
            'keterangan' => 'Belanja Alat Kebersihan',
            'stage_id' => $this->stage1->id,
            'arkas_id' => 'kas:SNAP-01',
            'content_hash' => 'hash-snap-01',
        ]);

        $snapshotSebelum = SyncSnapshotService::rekamSnapshot($this->sekolah->id, 2026, '2026-04-30');
        $this->assertFalse($snapshotSebelum->is_frozen);

        // 2. Ajukan dan Sahkan LPJ Tahap 1 oleh Dinas
        $lpj = LpjManifestService::ajukanLpj($this->sekolah->id, 2026, $this->stage1->id, $this->operator->id);
        LpjManifestService::sahkanLpj($lpj, $this->dinas, 'Disahkan sesuai bukti');

        // 3. Verifikasi TutupBuku terkunci
        $this->assertTrue(TutupBuku::terkunci($this->sekolah->id, 2026, $this->stage1->id));

        // 4. Verifikasi snapshot periode tersebut dibekukan
        $snapshotSesudah = SyncSnapshot::find($snapshotSebelum->id);
        $this->assertTrue($snapshotSesudah->is_frozen);
        $this->assertNotNull($snapshotSesudah->frozen_at);
        $this->assertSame($lpj->id, $snapshotSesudah->frozen_by_lpj_id);

        // 5. Upaya rekam ulang snapshot pada tanggal beku tidak merusak data beku
        $hashBeku = $snapshotSesudah->manifest_hash;
        $snapshotCobaUlang = SyncSnapshotService::rekamSnapshot($this->sekolah->id, 2026, '2026-04-30');
        $this->assertSame($hashBeku, $snapshotCobaUlang->manifest_hash);
    }

    public function test_verifikasi_integritas_mendeteksi_tampering_data_transaksi(): void
    {
        // 1. Buat transaksi sah
        $trx = KasTransaction::create([
            'school_id' => $this->sekolah->id,
            'tahun' => 2026,
            'type' => 'belanja',
            'tanggal' => '2026-05-12',
            'total' => 12000000,
            'keterangan' => 'Langganan Internet Sekolah',
            'stage_id' => $this->stage1->id,
            'arkas_id' => 'kas:AUDIT-01',
            'content_hash' => 'hash-audit-01',
        ]);

        $lpj = LpjManifestService::ajukanLpj($this->sekolah->id, 2026, $this->stage1->id, $this->operator->id);
        LpjManifestService::sahkanLpj($lpj, $this->dinas);

        // Verifikasi sebelum perubahan: Integritas Valid
        $this->assertTrue(LpjManifestService::verifikasiIntegritas($lpj));

        // 2. Lakukan manipulasi data transaksi (misal serangan DB: nominal digelembungkan)
        $trx->update(['total' => 20000000]);

        // Verifikasi setelah manipulasi: Integritas Rusak (Tamper Terdeteksi!)
        $this->assertFalse(LpjManifestService::verifikasiIntegritas($lpj), 'Sistem harus mendeteksi modifikasi liar setelah pengesahan LPJ');
    }
}
