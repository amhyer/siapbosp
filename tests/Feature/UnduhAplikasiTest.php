<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Unduh aplikasi portable per akun (Tahap A3): ZIP generik +
 * identitas sekolah disuntikkan, token lama nonaktif.
 */
class UnduhAplikasiTest extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private string $induk;

    private ?string $cadangan = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sekolah = School::create([
            'npsn' => '90000001', 'nama' => 'SD Negeri A', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);
        $this->operator = User::create([
            'name' => 'Op', 'email' => 'op@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
            'api_token' => hash('sha256', 'token-lama'),
        ]);
        $this->induk = storage_path('app/paket/SiapBOSP-Portable-v12.zip');
        // Amankan artefak asli bila ada (jangan hapus hasil build sungguhan).
        if (is_file($this->induk)) {
            $this->cadangan = $this->induk.'.cadangan-test';
            @unlink($this->cadangan);
            rename($this->induk, $this->cadangan);
        }
        @mkdir(dirname($this->induk), 0777, true);
        $zip = new ZipArchive();
        $zip->open($this->induk, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('SiapBOSP/launcher-windows/server.conf', "SERVER_URL=http://127.0.0.1:8000\n");
        $zip->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->induk);
        if ($this->cadangan && is_file($this->cadangan)) {
            rename($this->cadangan, $this->induk);
        }
        parent::tearDown();
    }

    public function test_unduh_menyuntik_identitas_dan_merotasi_token(): void
    {
        $res = $this->actingAs($this->operator)->get('/unduh-aplikasi');
        $res->assertOk();
        $this->assertStringContainsString('zip', $res->headers->get('Content-Type'));

        $tmp = tempnam(sys_get_temp_dir(), 'uji').'.zip';
        file_put_contents($tmp, $res->streamedContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $conf = $zip->getFromName('SiapBOSP/launcher-windows/akun-sekolah.conf');
        $this->assertStringContainsString('NPSN=90000001', $conf);
        $this->assertStringContainsString('JENJANG=SD', $conf);
        $this->assertStringContainsString('SEKOLAH=SD Negeri A', $conf);
        preg_match('/^TOKEN=(.+)$/m', $conf, $m);
        $this->assertNotEmpty($m[1]);
        $this->assertSame(hash('sha256', trim($m[1])), $this->operator->fresh()->api_token);
        $this->assertNotSame(hash('sha256', 'token-lama'), $this->operator->fresh()->api_token);
        $zip->close();
        @unlink($tmp);
    }

    public function test_tanpa_berkas_induk_404(): void
    {
        @unlink($this->induk);
        $this->actingAs($this->operator)->get('/unduh-aplikasi')->assertNotFound();
    }

    public function test_dinas_tanpa_sekolah_ditolak(): void
    {
        $dinas = User::create(['name' => 'D', 'email' => 'd@t.t', 'password' => 'p', 'role' => 'dinas', 'school_id' => null]);
        $this->actingAs($dinas)->get('/unduh-aplikasi')->assertStatus(403);
        $this->actingAs($dinas)->get('/unduh-pemasang')->assertStatus(403);
        $this->actingAs($dinas)->get('/unduh-identitas')->assertStatus(403);
    }

    public function test_unduh_identitas_menyajikan_conf_dengan_token_baru(): void
    {
        $res = $this->actingAs($this->operator)->get('/unduh-identitas');
        $res->assertOk();
        $this->assertStringContainsString('akun-sekolah.conf', (string) $res->headers->get('Content-Disposition'));

        $isi = $res->getContent();
        $this->assertStringContainsString('NPSN=90000001', $isi);
        $this->assertStringContainsString('SEKOLAH=SD Negeri A', $isi);
        $this->assertStringContainsString('PUSAT_URL=', $isi);
        preg_match('/^TOKEN=(.+)$/m', $isi, $m);
        $this->assertNotEmpty($m[1]);
        $this->assertSame(hash('sha256', trim($m[1])), $this->operator->fresh()->api_token);
        $this->assertNotSame(hash('sha256', 'token-lama'), $this->operator->fresh()->api_token);
    }

    public function test_unduh_pemasang_menyajikan_exe_dan_404_tanpa_berkas(): void
    {
        $exe = storage_path('app/paket/SiapBOSP-Setup-v12.exe');
        $cadangan = null;
        if (is_file($exe)) {
            $cadangan = $exe.'.cadangan-test';
            @unlink($cadangan);
            rename($exe, $cadangan);
        }
        @mkdir(dirname($exe), 0777, true);

        try {
            file_put_contents($exe, 'ISI-EXE-UJIAN');
            $res = $this->actingAs($this->operator)->get('/unduh-pemasang');
            $res->assertOk();
            $this->assertSame('ISI-EXE-UJIAN', $res->streamedContent());
            $this->assertStringContainsString('SiapBOSP-Setup-v12.exe', (string) $res->headers->get('Content-Disposition'));

            @unlink($exe);
            $this->actingAs($this->operator)->get('/unduh-pemasang')->assertNotFound();
        } finally {
            @unlink($exe);
            if ($cadangan && is_file($cadangan)) {
                rename($cadangan, $exe);
            }
        }
    }
}
