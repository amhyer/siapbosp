<?php

namespace Tests\Feature;

use App\Console\Commands\PasangLokal;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use App\Support\KonfigLokal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pemasang lokal (Tahap D): siapbos:pasang-lokal.
 * Membuat sekolah, akun bendahara, master referensi, dan token
 * Tarik-ARKAS secara otomatis — idempoten bila dijalankan ulang.
 */
class PasangLokalTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'siapbosp-uji-'.uniqid();
        mkdir($this->dir, 0777, true);
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'akun-sekolah.conf', implode("\n", [
            'NPSN=90000001',
            'SEKOLAH=SD Negeri Uji',
            'JENJANG=SD',
            'KECAMATAN=Uji',
            'KAB_KOTA=Kota Uji',
            'PROVINSI=Sulawesi Selatan',
            'PUSAT_URL=https://pusat.test',
            'TOKEN=rahasia-pusat',
        ])."\n");
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'server.conf', "SERVER_URL=http://127.0.0.1:8123\nPORT=8123\n");
    }

    protected function tearDown(): void
    {
        $this->hapusDir($this->dir);
        parent::tearDown();
    }

    private function hapusDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private function opsi(): array
    {
        return [
            '--nama' => 'Bendahara Uji',
            '--email' => 'bendahara@uji.lokal',
            '--sandi' => 'rahasia123',
            '--tahun' => '2026',
            '--akun-conf' => $this->dir.DIRECTORY_SEPARATOR.'akun-sekolah.conf',
            '--agen-conf' => $this->dir.DIRECTORY_SEPARATOR.'agen.conf',
        ];
    }

    public function test_pasang_membuat_sekolah_akun_master_dan_token(): void
    {
        $this->artisan('siapbos:pasang-lokal', $this->opsi())->assertExitCode(0);

        $s = School::where('npsn', '90000001')->first();
        $this->assertNotNull($s);
        $this->assertSame('SD Negeri Uji', $s->nama);
        $this->assertSame('Kota Uji', $s->kab_kota);
        $this->assertSame('SD', $s->jenjang);

        $u = User::where('email', 'bendahara@uji.lokal')->first();
        $this->assertNotNull($u);
        $this->assertSame('operator', $u->role);
        $this->assertSame($s->id, $u->school_id);
        $this->assertTrue(Hash::check('rahasia123', $u->password));

        $this->assertSame(59, Rekening::count());
        $this->assertSame(6, RekeningPajak::count());
        $this->assertSame(5, Stage::where('tahun', 2026)->count());

        $conf = KonfigLokal::baca($this->dir.DIRECTORY_SEPARATOR.'agen.conf');
        $this->assertSame('http://127.0.0.1:8123', $conf['SERVER']);
        $this->assertSame('2026', $conf['TAHUN']);
        $this->assertNotEmpty($conf['TOKEN']);
        $this->assertSame(hash('sha256', $conf['TOKEN']), $u->fresh()->api_token);
    }

    public function test_pasang_ulang_idempoten_dan_merotasi_token_lokal(): void
    {
        $this->artisan('siapbos:pasang-lokal', $this->opsi())->assertExitCode(0);
        $token1 = KonfigLokal::baca($this->dir.DIRECTORY_SEPARATOR.'agen.conf')['TOKEN'];

        $this->artisan('siapbos:pasang-lokal', $this->opsi())->assertExitCode(0);
        $conf2 = KonfigLokal::baca($this->dir.DIRECTORY_SEPARATOR.'agen.conf');

        $this->assertSame(1, School::where('npsn', '90000001')->count());
        $this->assertSame(1, User::where('email', 'bendahara@uji.lokal')->count());
        $this->assertSame(59, Rekening::count());
        $this->assertSame(30, Stage::count());
        $this->assertNotSame($token1, $conf2['TOKEN']);
        $this->assertSame(
            hash('sha256', $conf2['TOKEN']),
            User::where('email', 'bendahara@uji.lokal')->first()->api_token
        );
    }

    public function test_sandi_pendek_dan_email_salah_ditolak(): void
    {
        $opsi = $this->opsi();
        $opsi['--sandi'] = '123';
        $this->artisan('siapbos:pasang-lokal', $opsi)->assertExitCode(1);
        $this->assertSame(0, School::count());

        $opsi = $this->opsi();
        $opsi['--email'] = 'bukan-email';
        $this->artisan('siapbos:pasang-lokal', $opsi)->assertExitCode(1);
        $this->assertSame(0, School::count());
    }

    public function test_status_belum_terpasang_lalu_siap(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->artisan('siapbos:pasang-lokal', ['--status' => true])->assertExitCode(1);

        $this->artisan('siapbos:pasang-lokal', $this->opsi())->assertExitCode(0);
        $this->artisan('siapbos:pasang-lokal', ['--status' => true])->assertExitCode(0);
    }

    /**
     * DB_DATABASE relatif harus diukur dari folder aplikasi (base_path),
     * bukan dari folder kerja proses — kalau tidak, pemasang yang dijalankan
     * dari folder ekstrak akan menulis database ke tempat yang salah.
     */
    public function test_path_database_relatif_diukur_dari_folder_aplikasi(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => 'database/database.sqlite',
        ]);

        $cmd = new PasangLokal;
        $metode = new \ReflectionMethod($cmd, 'pathDatabase');
        $metode->setAccessible(true);

        $this->assertSame(base_path('database/database.sqlite'), $metode->invoke($cmd));
        $this->assertSame(base_path('database/database.sqlite'), config('database.connections.sqlite.database'));

        config(['database.connections.sqlite.database' => 'C:\\data\\sekolah.sqlite']);
        $this->assertSame('C:\\data\\sekolah.sqlite', $metode->invoke($cmd));

        config(['database.connections.sqlite.database' => ':memory:']);
        $this->assertSame(':memory:', $metode->invoke($cmd));
    }
}
