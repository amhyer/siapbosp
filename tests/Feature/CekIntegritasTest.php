<?php

namespace Tests\Feature;

use App\Jobs\ProsesImporArkas;
use App\Models\ArkasImportRun;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Services\ArkasImportService;
use App\Services\CekIntegritasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Cek Ulang Integritas Impor" — perbandingan data ARKAS vs pencatatan
 * SiapBOSP per bulan (untuk Hotline: selisih pasca pergeseran/perubahan).
 */
class CekIntegritasTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private string $mini;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000001', 'nama' => 'SD Negeri A', 'jenjang' => 'SD',
            'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);
        $this->operator = User::create([
            'name' => 'Operator A', 'email' => 'a@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
        BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'Honor Pengelola', 'tipe' => 'belanja']);
        RekeningPajak::create(['kode' => 'pph21', 'nama' => 'PPh Pasal 21']);
        for ($i = 1; $i <= 5; $i++) {
            Stage::create(['tahun' => 2025, 'nama' => "Tahap $i", 'urutan' => $i, 'is_open' => true]);
        }
        $this->mini = base_path('tests/Fixtures/arkas-mini.xlsx');
    }

    public function test_bulan_dibandingkan_per_bulan(): void
    {
        $hasil = CekIntegritasService::bandingkan($this->mini, $this->sekolah, 2025);

        $this->assertCount(12, $hasil['bulan']);
        $this->assertSame(2025, $hasil['tahun']);
        // Fixture KAS_Umum: Jan (terima dana), Feb (belanja ATK + setor PPh 21),
        // Mar (honor), Apr (tarik tunai) — sisanya baris saldo/counterpart.
        $this->assertSame(5, $hasil['total']['arkas_jumlah']);
        $this->assertSame(2, $hasil['bulan'][2]['arkas']['jumlah']);
        $this->assertSame(1, $hasil['bulan'][1]['arkas']['jumlah']);
    }

    public function test_setelah_impor_bulan_cocok(): void
    {
        ArkasImportService::import($this->mini, $this->sekolah->id, $this->operator->id, 2025);

        $hasil = CekIntegritasService::bandingkan($this->mini, $this->sekolah, 2025);

        $this->assertSame('cocok', $hasil['bulan'][1]['status']);
        $this->assertSame('cocok', $hasil['bulan'][2]['status']);
        $this->assertSame(0, $hasil['total']['bulan_berbeda']);
        $this->assertSame($hasil['total']['arkas_jumlah'], $hasil['total']['lokal_jumlah']);
    }

    public function test_tanpa_impor_bulan_terdeteksi_berbeda(): void
    {
        $hasil = CekIntegritasService::bandingkan($this->mini, $this->sekolah, 2025);

        $this->assertSame('beda', $hasil['bulan'][2]['status']);
        $this->assertSame(2, $hasil['bulan'][2]['selisih_jumlah']);
        $this->assertGreaterThan(0, $hasil['total']['bulan_berbeda']);
        $this->assertStringContainsString('Impor ARKAS', implode(' ', $hasil['catatan']));
    }

    public function test_halaman_cek_integritas_terbuka(): void
    {
        $this->actingAs($this->operator)
            ->get('/impor-arkas/cek-integritas')
            ->assertOk()
            ->assertSee('Cek Ulang Integritas Impor');
    }

    public function test_unggahan_cek_integritas_diproses_asinkron_dan_berkasnya_privat(): void
    {
        Queue::fake();
        Storage::fake('local');
        $response = $this->actingAs($this->operator)->post('/impor-arkas/cek-integritas', [
            'tahun' => 2025,
            'berkas' => new UploadedFile(
                $this->mini, 'arkas-mini.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true
            ),
        ]);

        $run = ArkasImportRun::firstOrFail();
        $response->assertRedirect(route('impor-arkas.status', $run));
        $this->assertSame(ArkasImportRun::JENIS_INTEGRITAS, $run->jenis);
        $this->assertSame('queued', $run->status);
        Storage::disk('local')->assertExists($run->file_path);
        Queue::assertPushed(ProsesImporArkas::class);
    }

    public function test_tahun_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->operator)
            ->from('/impor-arkas/cek-integritas')
            ->post('/impor-arkas/cek-integritas', ['tahun' => 2010])
            ->assertRedirect('/impor-arkas/cek-integritas')
            ->assertSessionHasErrors('tahun');
    }

    public function test_tanpa_berkas_di_server_pusat_diarahkan(): void
    {
        $this->actingAs($this->operator)
            ->from('/impor-arkas/cek-integritas')
            ->post('/impor-arkas/cek-integritas', ['tahun' => 2025])
            ->assertRedirect('/impor-arkas/cek-integritas')
            ->assertSessionHas('error');
    }
}
