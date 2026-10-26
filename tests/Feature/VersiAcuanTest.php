<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Versi SiapBOSP dan versi acuan ARKAS adalah DUA hal terpisah.
 *
 * Sebelumnya angka "ARKAS 4 v4.3.0" ditulis harfiah di tiga view, sehingga
 * update ARKAS berisiko meninggalkan teks basi — dan badge menampilkan angka
 * yang sama dua kali tanpa penjelasan. Kini:
 *   - `app.version`      = versi SiapBOSP sendiri (APP_VERSION),
 *   - `app.arkas_acuan`  = ARKAS 4 yang jadi acuan SiapBOSP (ARKAS_ACUAN),
 *   - versi ARKAS yang terpasang tetap dideteksi terpisah (VersiArkasService).
 */
class VersiAcuanTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();

        $sekolah = School::create([
            'npsn' => '90000071', 'nama' => 'SD Versi', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z',
        ]);

        $this->op = User::create([
            'name' => 'Op Versi', 'email' => 'op-versi@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $sekolah->id,
        ]);
    }

    private function halaman(string $url, int $tahun = 2026): string
    {
        return $this->actingAs($this->op)->withSession(['tahun' => $tahun])
            ->get($url)->assertOk()->getContent();
    }

    /** Kedua kunci config ada, terpisah, dan tidak kosong. */
    public function test_config_versi_dan_acuan_arkas_terpisah(): void
    {
        $versi = config('app.version');
        $acuan = config('app.arkas_acuan');

        $this->assertIsString($versi);
        $this->assertNotSame('', trim($versi),
            'app.version kosong — badge topbar akan tampil tanpa angka.');
        $this->assertIsString($acuan);
        $this->assertNotSame('', trim($acuan),
            'app.arkas_acuan kosong — teks "Selaras ARKAS 4 v…" akan menggantung.');

        // Bukan nilai yang sama yang dipakai ulang: kunci berbeda di config.
        $config = (array) require config_path('app.php');
        $this->assertArrayHasKey('version', $config);
        $this->assertArrayHasKey('arkas_acuan', $config);
    }

    /**
     * Tempat yang dulu menulis angka acuan secara harfiah kini mengikuti config:
     * footer kerangka, judul badge versi, halaman Bantuan, panel pajak di wizard
     * belanja, serta subtitle dan pesan kelengkapan Kertas Kerja.
     */
    public function test_tiga_teks_acuan_arkas_mengikuti_config(): void
    {
        config(['app.arkas_acuan' => '9.9.9']);

        $dash = $this->halaman(route('dashboard'));
        $bantuan = $this->halaman(route('bantuan'));

        // Footer kerangka.
        $this->assertStringContainsString('Selaras ARKAS 4 v9.9.9', $dash);
        // Judul badge versi di topbar.
        $this->assertStringContainsString('selaras ARKAS 4 v9.9.9', $dash);
        // Halaman Bantuan.
        $this->assertStringContainsString('ARKAS 4 v9.9.9', $bantuan);

        // Angka lama tidak boleh tertinggal di ketiga tempat itu.
        $this->assertStringNotContainsString('ARKAS 4 v4.3.0', $dash);
        $this->assertStringNotContainsString('ARKAS 4 v4.3.0', $bantuan);
    }

    /** Versi SiapBOSP sendiri bisa diganti tanpa menyentuh acuan ARKAS. */
    public function test_versi_siapbosp_bisa_diganti_tanpa_mengubah_acuan_arkas(): void
    {
        config(['app.version' => 'v1.2.3', 'app.arkas_acuan' => '4.3.0']);

        $dash = $this->halaman(route('dashboard'));
        $cetak = $this->halaman(route('bku.cetak', ['bulan' => '2026-02']));

        $this->assertStringContainsString('⟳ v1.2.3', $dash);
        $this->assertStringContainsString('Dicetak dari <b>SiapBOSP v1.2.3</b>', $dash);
        $this->assertStringContainsString('Dicetak dari <b>SiapBOSP v1.2.3</b>', $cetak);

        // Acuan ARKAS tetap sesuai config-nya sendiri.
        $this->assertStringContainsString('Selaras ARKAS 4 v4.3.0', $dash);
    }

    /**
     * Pagar statis: tidak boleh ada lagi angka acuan ARKAS yang ditulis harfiah
     * di view. Bila ARKAS naik versi, satu kunci config cukup.
     */
    public function test_tidak_ada_angka_acuan_arkas_harfiah_di_view(): void
    {
        $pelanggaran = [];
        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($berkas as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            $isi = (string) file_get_contents($f->getPathname());
            // Komentar Blade tidak dirender, jadi tidak dihitung pelanggaran.
            $tampil = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $isi);
            if (preg_match('/ARKAS 4 v4\.3\.0/i', $tampil)) {
                $pelanggaran[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $f->getPathname());
            }
        }

        $this->assertSame([], $pelanggaran,
            'Angka acuan ARKAS masih harfiah di view (pakai config(\'app.arkas_acuan\')): '
            .implode(', ', $pelanggaran));
    }

    /** .env.example mendokumentasikan kedua kunci agar tidak jadi misteri. */
    public function test_env_example_mendokumentasikan_kedua_kunci_versi(): void
    {
        $contoh = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_VERSION=', $contoh);
        $this->assertStringContainsString('ARKAS_ACUAN=', $contoh);
        $this->assertStringContainsString('TIDAK mengikuti ARKAS', $contoh,
            'Komentar .env.example harus menjelaskan bahwa APP_VERSION tidak mengikuti ARKAS.');
    }
}
