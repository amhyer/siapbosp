<?php

namespace Tests\Feature;

use App\Jobs\ProsesImporArkas;
use App\Models\ArkasImportRun;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Services\ArkasImportService;
use App\Services\RekonsiliasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rekonsiliasi ARKAS ↔ SiapBOS: baca-saja, presisi sampai nomor BKU.
 */
class RekonsiliasiTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private string $mini;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mini = base_path('tests/Fixtures/arkas-mini.xlsx');
        $this->sekolah = School::create([
            'npsn' => '90000001', 'nama' => 'SD R', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->operator = User::create([
            'name' => 'Op', 'email' => 'opr@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
        BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'H', 'tipe' => 'belanja']);
        RekeningPajak::create(['kode' => 'pph21', 'nama' => 'P']);
        for ($i = 1; $i <= 5; $i++) {
            Stage::create(['tahun' => 2025, 'nama' => "Tahap $i", 'urutan' => $i, 'is_open' => true]);
        }
        $this->actingAs($this->operator);
        ArkasImportService::import($this->mini, $this->sekolah->id, $this->operator->id, 2025);
    }

    public function test_rekonsiliasi_cocok_penuh_setelah_impor(): void
    {
        $h = RekonsiliasiService::bandingkan($this->sekolah->id, $this->mini, 2025);
        $this->assertGreaterThan(0, $h['cocok']);
        $this->assertSame([], $h['selisih_nominal']);
        $this->assertSame([], $h['hilang_di_sipbos']);
        $this->assertSame([], $h['lebih_di_sipbos']);
        $this->assertNotEmpty($h['peta_bku']);
        $this->assertCount(12, $h['saldo_bulanan']);
    }

    public function test_rekonsiliasi_mendeteksi_selisih_dan_hilang(): void
    {
        $t = KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolah->id)->where('keterangan', 'Belanja ATK')->firstOrFail();
        $t->update(['total' => (int) $t->total + 1000]);
        KasTransaction::withoutGlobalScope('school')
            ->where('school_id', $this->sekolah->id)->where('keterangan', 'Tarik Tunai')->delete();

        $h = RekonsiliasiService::bandingkan($this->sekolah->id, $this->mini, 2025);
        $this->assertCount(1, $h['selisih_nominal']);
        $this->assertCount(1, $h['hilang_di_sipbos']);
    }

    public function test_unggahan_rekonsiliasi_diproses_asinkron_dan_berkasnya_privat(): void
    {
        Queue::fake();
        Storage::fake('local');
        $response = $this->actingAs($this->operator)->post('/rekonsiliasi', [
            'tahun' => 2025,
            'berkas' => new UploadedFile(
                $this->mini, 'arkas-mini.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true
            ),
        ]);

        $run = ArkasImportRun::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('impor-arkas.status', $run));
        $this->assertSame(ArkasImportRun::JENIS_REKONSILIASI, $run->jenis);
        $this->assertSame('queued', $run->status);
        Storage::disk('local')->assertExists($run->file_path);
        Queue::assertPushed(ProsesImporArkas::class);
    }

    public function test_halaman_rekonsiliasi_bisa_dibuka_operator(): void
    {
        $this->actingAs($this->operator)->get('/rekonsiliasi')->assertOk();
    }
}
