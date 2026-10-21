<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\KasTransaction;
use App\Models\LpjSubmission;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\TutupBuku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hapus data sekolah: boleh untuk semua peran KECUALI kepala sekolah.
 * Operator menghapus sekolahnya sendiri; dinas/superadmin sekolah mana pun.
 */
class HapusDataTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolahA;

    private School $sekolahB;

    private User $operatorA;

    private User $kepsekA;

    private User $dinas;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->sekolahA = School::create([
            'npsn' => '90000101', 'nama' => 'SD A', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->sekolahB = School::create([
            'npsn' => '90000102', 'nama' => 'SD B', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->operatorA = User::create([
            'name' => 'Op A', 'email' => 'opa@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolahA->id,
        ]);
        $this->kepsekA = User::create([
            'name' => 'Kepsek A', 'email' => 'ksa@t.t', 'password' => 'p',
            'role' => 'kepsek', 'school_id' => $this->sekolahA->id,
        ]);
        $this->dinas = User::create([
            'name' => 'Dinas', 'email' => 'd@t.t', 'password' => 'p', 'role' => 'dinas',
        ]);
        Stage::create(['tahun' => 2025, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);

        foreach ([$this->sekolahA, $this->sekolahB] as $s) {
            $t = KasTransaction::withoutGlobalScope('school')->create([
                'school_id' => $s->id, 'tahun' => 2025, 'type' => 'belanja',
                'no_bku' => 1, 'tanggal' => '2025-02-01', 'metode_bayar' => 'tunai',
                'keterangan' => 'Belanja', 'total' => 1000,
            ]);
            $path = 'lampiran/'.$s->id.'/bukti.pdf';
            Storage::put($path, 'isi');
            $t->attachments()->create(['path' => $path, 'nama' => 'bukti.pdf', 'ukuran' => 3]);
            $h = RkasHeader::withoutGlobalScope('school')->create([
                'school_id' => $s->id, 'tahun' => 2025, 'revisi_ke' => 1, 'status' => 'draft',
            ]);
            $h->details()->create([
                'rekening_id' => Rekening::create(
                    ['kode' => '5.9.'.$s->id, 'nama' => 'R', 'tipe' => 'belanja'])->id,
                'uraian' => 'U', 'harga_satuan' => 1, 'jumlah' => 1,
            ]);
            TutupBuku::withoutGlobalScope('school')->create([
                'school_id' => $s->id, 'tahun' => 2025, 'stage_id' => Stage::first()->id,
            ]);
            LpjSubmission::withoutGlobalScope('school')->create([
                'school_id' => $s->id, 'tahun' => 2025, 'stage_id' => Stage::first()->id,
            ]);
            Penandatangan::create(['school_id' => $s->id, 'nama' => 'TTD']);
        }
    }

    private function jmlA(string $model): int
    {
        return $model::withoutGlobalScope('school')->where('school_id', $this->sekolahA->id)->count();
    }

    public function test_operator_menghapus_data_sekolahnya_sendiri(): void
    {
        $res = $this->actingAs($this->operatorA)->get('/utility/hapus-data');
        $res->assertOk();

        $res = $this->actingAs($this->operatorA)->delete('/utility/hapus-data', [
            'konfirmasi' => '90000101',
        ]);
        $res->assertRedirect('/dashboard');

        $this->assertSame(0, $this->jmlA(KasTransaction::class));
        $this->assertSame(0, $this->jmlA(RkasHeader::class));
        $this->assertSame(0, $this->jmlA(TutupBuku::class));
        $this->assertSame(0, $this->jmlA(LpjSubmission::class));
        $this->assertFalse(Storage::exists('lampiran/'.$this->sekolahA->id.'/bukti.pdf'));
        // Yang dipertahankan:
        $this->assertNotNull(School::find($this->sekolahA->id));
        $this->assertSame(2, User::where('school_id', $this->sekolahA->id)->count());
        $this->assertSame(1, Penandatangan::where('school_id', $this->sekolahA->id)->count());
        $this->assertSame(1, ActivityLog::where('school_id', $this->sekolahA->id)
            ->where('aksi', 'hapus_data')->count());
        // Sekolah B utuh:
        $this->assertSame(1, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahB->id)->count());
    }

    public function test_operator_gagal_bila_konfirmasi_salah(): void
    {
        $this->actingAs($this->operatorA)->delete('/utility/hapus-data', [
            'konfirmasi' => 'salah',
        ])->assertSessionHas('error');
        $this->assertSame(1, $this->jmlA(KasTransaction::class));
    }

    public function test_kepsek_ditolak_403(): void
    {
        $this->actingAs($this->kepsekA)->get('/utility/hapus-data')->assertForbidden();
        $this->actingAs($this->kepsekA)->delete('/utility/hapus-data', [
            'konfirmasi' => '90000101',
        ])->assertForbidden();
        $this->assertSame(1, $this->jmlA(KasTransaction::class));
    }

    public function test_dinas_menghapus_data_sekolah_mana_pun(): void
    {
        $this->actingAs($this->dinas)->delete(
            '/dinas/sekolah/'.$this->sekolahA->id.'/hapus-data')->assertRedirect();
        $this->assertSame(0, $this->jmlA(KasTransaction::class));
        $this->assertSame(0, $this->jmlA(LpjSubmission::class));
    }

    public function test_operator_ditolak_menghapus_via_jalur_dinas(): void
    {
        $this->actingAs($this->operatorA)->delete(
            '/dinas/sekolah/'.$this->sekolahB->id.'/hapus-data')->assertForbidden();
        $this->assertSame(1, KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolahB->id)->count());
    }
}
