<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jejak audit penghapusan transaksi kas.
 *
 * ARKAS 4 tidak mengenal hapus langsung (koreksi lewat pengajuan ke pusat).
 * SiapBOSP harus bisa dipakai offline, jadi hapus tetap tersedia — tetapi
 * WAJIB beralasan dan meninggalkan baris di `activity_logs` yang append-only,
 * supaya pemeriksa tahu apa yang dihapus, oleh siapa, dan mengapa.
 *
 * Sebelum ini `delete()` berjalan tanpa catatan apa pun.
 */
class JejakHapusTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    private int $bosId;

    private int $rekPendapatan;

    private int $rekBelanja;

    private Stage $tahap;

    protected function setUp(): void
    {
        parent::setUp();

        $sekolah = School::create([
            'npsn' => '90000081', 'nama' => 'SD Jejak', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);

        $this->op = User::create([
            'name' => 'Bendahara Jejak', 'email' => 'jejak@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $sekolah->id,
        ]);

        $this->bosId = BosType::firstOrCreate(['kode' => 'reguler'], ['nama' => 'BOS Reguler'])->id;
        $this->rekPendapatan = Rekening::firstOrCreate(['kode' => '4.1.1.1.1.0001'],
            ['nama' => 'Bantuan Provinsi', 'tipe' => 'pendapatan'])->id;
        $this->rekBelanja = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'],
            ['nama' => 'Honor Pengelola', 'tipe' => 'belanja'])->id;

        $this->tahap = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        Allocation::create([
            'school_id' => $sekolah->id, 'tahun' => 2026, 'bos_type_id' => $this->bosId,
            'stage_id' => null, 'nilai' => 50_000_000,
        ]);

        $this->actingAs($this->op);
    }

    private function pendapatan(string $tanggal = '2026-02-10', int $nilai = 5_000_000): KasTransaction
    {
        $this->withSession(['tahun' => 2026])->post(route('pendapatan.store'), [
            'tanggal' => $tanggal, 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap->id,
            'keterangan' => 'Pencairan '.$tanggal,
            'rekening_id' => [$this->rekPendapatan], 'nilai' => [$nilai],
        ])->assertSessionHas('ok');

        return KasTransaction::orderByDesc('id')->firstOrFail();
    }

    private function belanja(string $tanggal = '2026-03-15', int $nilai = 2_000_000): KasTransaction
    {
        $this->withSession(['tahun' => 2026])->post(route('belanja.store'), [
            'tanggal' => $tanggal, 'bos_type_id' => $this->bosId, 'stage_id' => $this->tahap->id,
            'metode_bayar' => 'transfer', 'keterangan' => 'Belanja '.$tanggal,
            'rekening_id' => [$this->rekBelanja], 'nilai' => [$nilai],
        ])->assertSessionHas('ok');

        return KasTransaction::orderByDesc('id')->firstOrFail();
    }

    /** Tanpa alasan: ditolak, transaksi tetap ada, tidak ada jejak yang mengarang. */
    public function test_hapus_tanpa_alasan_ditolak_dan_transaksi_tetap_ada(): void
    {
        $t = $this->pendapatan();

        $this->withSession(['tahun' => 2026])->delete(route('penerimaan.destroy', $t))
            ->assertSessionHasErrors('alasan');

        $this->assertDatabaseHas('kas_transactions', ['id' => $t->id]);
        $this->assertDatabaseMissing('activity_logs', ['aksi' => 'hapus_transaksi']);
    }

    /** Alasan terlalu pendek juga ditolak (agar jejak audit tetap terbaca). */
    public function test_alasan_kependekan_ditolak(): void
    {
        $t = $this->pendapatan();

        $this->withSession(['tahun' => 2026])
            // 'salah' = 5 karakter, masih lolos min:5 — pakai yang benar-benar pendek.
            ->delete(route('penerimaan.destroy', $t), ['alasan' => 'eh'])
            ->assertSessionHasErrors('alasan');

        $this->assertDatabaseHas('kas_transactions', ['id' => $t->id]);
        $this->assertDatabaseMissing('activity_logs', ['aksi' => 'hapus_transaksi']);
    }

    /** Dengan alasan: transaksi terhapus DAN tercatat lengkap di Log Sistem. */
    public function test_hapus_pendapatan_beralasan_tercatat_di_log_sistem(): void
    {
        $t = $this->pendapatan('2026-02-10', 5_000_000);
        $noBku = $t->no_bku;

        $this->withSession(['tahun' => 2026])
            ->delete(route('penerimaan.destroy', $t), ['alasan' => 'salah input nominal pencairan'])
            ->assertSessionHas('ok');

        $this->assertDatabaseMissing('kas_transactions', ['id' => $t->id]);

        $log = ActivityLog::where('aksi', 'hapus_transaksi')->firstOrFail();
        $this->assertSame('Bendahara Jejak', $log->user_name);
        $this->assertSame(auth()->id(), $log->user_id);
        $this->assertStringContainsString('Hapus Pendapatan', $log->keterangan);
        $this->assertStringContainsString('10/02/2026', $log->keterangan);
        $this->assertStringContainsString('BKU#'.$noBku, $log->keterangan);
        $this->assertStringContainsString('Rp 5.000.000', $log->keterangan);
        $this->assertStringContainsString('salah input nominal pencairan', $log->keterangan);

        // Terlihat oleh operator di halaman Log Sistem.
        $html = $this->withSession(['tahun' => 2026])->get('/pengaturan/log')->assertOk()->getContent();
        $this->assertStringContainsString('salah input nominal pencairan', $html);
    }

    /** Belanja juga tercatat, dengan sebutan jenis yang benar. */
    public function test_hapus_belanja_beralasan_tercatat_sebagai_belanja(): void
    {
        $this->pendapatan();
        $b = $this->belanja('2026-03-15', 2_000_000);

        $this->withSession(['tahun' => 2026])
            ->delete(route('pengeluaran.destroy', $b), ['alasan' => 'nota dobel dengan transaksi lain'])
            ->assertSessionHas('ok');

        $this->assertDatabaseMissing('kas_transactions', ['id' => $b->id]);

        $log = ActivityLog::where('aksi', 'hapus_transaksi')->firstOrFail();
        $this->assertStringContainsString('Hapus Belanja', $log->keterangan);
        $this->assertStringContainsString('nota dobel', $log->keterangan);
    }

    /**
     * Pagar lama harus tetap menang: bulan yang sudah ditutup menolak hapus
     * SEBELUM jejak dibuat — log tidak boleh mengaku ada penghapusan yang
     * sebenarnya tidak terjadi.
     */
    public function test_bulan_tertutup_menolak_hapus_dan_tidak_menulis_jejak(): void
    {
        $t = $this->pendapatan('2026-02-10', 5_000_000);
        $this->withSession(['tahun' => 2026])->post(route('bku.tutup'), ['bulan' => '2026-02'])
            ->assertSessionHas('ok');

        $this->withSession(['tahun' => 2026])
            ->delete(route('penerimaan.destroy', $t), ['alasan' => 'mau saya hapus saja'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('kas_transactions', ['id' => $t->id]);
        $this->assertDatabaseMissing('activity_logs', ['aksi' => 'hapus_transaksi']);
    }

    /** Jejak audit tetap append-only: baris hapus tidak bisa diubah/dihapus. */
    public function test_jejak_hapus_tidak_bisa_diubah_atau_dihapus(): void
    {
        $t = $this->pendapatan();
        $this->withSession(['tahun' => 2026])
            ->delete(route('penerimaan.destroy', $t), ['alasan' => 'koreksi input ganda']);

        $log = ActivityLog::where('aksi', 'hapus_transaksi')->firstOrFail();

        try {
            $log->update(['keterangan' => 'dirapikan']);
            $this->fail('Jejak audit seharusnya tidak bisa diubah.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $log->delete();
            $this->fail('Jejak audit seharusnya tidak bisa dihapus.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak boleh dihapus', $e->getMessage());
        }
    }
}
