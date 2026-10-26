<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\User;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Paritas tampilan untuk SELURUH halaman (bukan hanya halaman contoh).
 *
 * Kerangka `layouts.app` adalah satu-satunya kerangka halaman aplikasi:
 * sidebar gelap (.navw), header judul, dan footer "Selaras ARKAS 4". Setiap
 * halaman yang ditautkan dari sidebar wajib memakai kerangka itu, dan setiap
 * rute GET tanpa parameter wajib bisa dibuka tanpa galat 500.
 *
 * Uji ini menelusuri tautan langsung dari HTML sidebar, bukan daftar manual,
 * sehingga halaman baru otomatis ikut terjaga tanpa menyunting uji.
 */
class ParitasSemuaHalamanTest extends TestCase
{
    use RefreshDatabase;

    /** Tanda kerangka yang wajib ada di setiap halaman aplikasi. */
    private const TANDA_KERANGKA = [
        'id="sidebar"' => 'sidebar',
        '<nav class="navw' => 'navigasi .navw',
        'Selaras ARKAS 4' => 'footer ARKAS 4',
    ];

    private School $school;

    private User $op;

    private User $dinas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterSeeder::class);

        $this->school = School::create([
            'npsn' => '40309911', 'nama' => 'SDN Paritas Semua', 'jenjang' => 'SD',
            'status_sekolah' => 'negeri', 'jumlah_siswa' => 120, 'satuan_biaya_bosp' => 1_000_000,
            'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->op = User::create([
            'name' => 'Bendahara Paritas', 'email' => 'paritas-op@sdn.test', 'password' => 'secret123',
            'role' => 'operator', 'school_id' => $this->school->id,
        ]);

        $this->dinas = User::create([
            'name' => 'Dinas Paritas', 'email' => 'paritas-dinas@sdn.test', 'password' => 'secret123',
            'role' => 'dinas', 'school_id' => null,
        ]);

        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        Allocation::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'tahun' => 2026, 'bos_type_id' => $bos->id,
            'nilai' => 120_000_000,
        ]);
        RkasHeader::create([
            'school_id' => $this->school->id, 'tahun' => 2026, 'revisi_ke' => 1, 'status' => 'draft',
            'sumber_dana' => 'BOS Reguler', 'jenis_rkas' => 'murni',
        ]);
    }

    /**
     * Setiap tautan di sidebar membuka halaman yang memakai kerangka sama:
     * sidebar, satu judul halaman, dan footer ARKAS 4.
     */
    public function test_tautan_sidebar_sekolah_membuka_halaman_berkerangka_sama(): void
    {
        $tautan = $this->tautanSidebar('/dashboard', $this->op);

        $this->assertGreaterThanOrEqual(30, count($tautan),
            'Tautan sidebar terlalu sedikit ('.count($tautan).') — blok <nav class="navw"> berubah bentuk?');

        $this->assertHalamanBerkerangka($tautan, $this->op);
    }

    /** Sidebar dinas (pengawas) memakai kerangka yang sama. */
    public function test_tautan_sidebar_dinas_membuka_halaman_berkerangka_sama(): void
    {
        $tautan = $this->tautanSidebar('/dinas', $this->dinas);

        $this->assertGreaterThanOrEqual(10, count($tautan),
            'Tautan sidebar dinas terlalu sedikit ('.count($tautan).').');

        $this->assertHalamanBerkerangka($tautan, $this->dinas);
    }

    /**
     * Tidak ada rute GET tanpa parameter yang berakhir 500. Rute biner
     * (unduhan/ekspor PDF-XLSX-CSV) dilewati karena isinya bukan halaman.
     */
    public function test_semua_rute_get_tanpa_parameter_tidak_galat_500(): void
    {
        $lewati = ['api/', 'login', 'logout', '_aset', '_arkas', 'lampiran/',
            'unduh', 'unduh-aplikasi', 'unduh-pemasang', 'unduh-identitas',
            '.csv', '.xlsx', '.pdf', 'template', 'backup/unduh'];

        $gagal = [];
        foreach (Route::getRoutes() as $rute) {
            if (! in_array('GET', $rute->methods(), true)) {
                continue;
            }
            $uri = $rute->uri();
            if (str_contains($uri, '{')) {
                continue;
            }
            if (array_filter($lewati, fn ($p) => str_contains($uri, $p)) !== []) {
                continue;
            }

            $res = $this->withSession(['tahun' => 2026])->actingAs($this->op)->get('/'.$uri);
            $kode = $res->getStatusCode();
            if ($kode >= 500) {
                $gagal[] = '/'.$uri.' → HTTP '.$kode;
            }
        }

        $this->assertSame([], $gagal,
            "Rute GET tanpa parameter berakhir galat server:\n".implode("\n", $gagal));
    }

    /** Ambil href dari blok <nav class="navw"> halaman tertentu. */
    private function tautanSidebar(string $halaman, User $pengguna): array
    {
        $html = (string) $this->withSession(['tahun' => 2026])->actingAs($pengguna)
            ->get($halaman)->assertOk()->getContent();

        preg_match('#<nav class="navw.*?</nav>#s', $html, $blok);
        $this->assertNotSame('', $blok[0] ?? '',
            'Blok <nav class="navw"> tidak ditemukan di '.$halaman.' — kerangka sidebar berubah.');

        preg_match_all('#href="([^"]+)"#', $blok[0], $cocok);

        // route() menghasilkan URL absolut (http://localhost/rkas), jadi
        // ambil bagian path-nya agar bisa diminta langsung oleh $this->get().
        $tautan = [];
        foreach ($cocok[1] as $u) {
            $path = parse_url($u, PHP_URL_PATH);
            if (is_string($path) && $path !== '' && $path !== '/keluar') {
                $tautan[$path] = true;
            }
        }

        return array_keys($tautan);
    }

    /** @param  list<string>  $tautan */
    private function assertHalamanBerkerangka(array $tautan, User $pengguna): void
    {
        $gagal = [];

        foreach ($tautan as $u) {
            $res = $this->followingRedirects()->withSession(['tahun' => 2026])
                ->actingAs($pengguna)->get($u);
            $kode = $res->getStatusCode();
            if ($kode !== 200) {
                $gagal[] = $u.' → HTTP '.$kode;

                continue;
            }

            // Unduhan (mis. /dinas/backup → berkas .sql) bukan halaman:
            // tidak diwajibkan memakai kerangka.
            if (! str_contains((string) $res->headers->get('Content-Type'), 'text/html')) {
                continue;
            }

            $isi = (string) $res->getContent();
            foreach (self::TANDA_KERANGKA as $tanda => $nama) {
                if (! str_contains($isi, $tanda)) {
                    $gagal[] = $u.' → tidak memakai '.$nama;
                }
            }

            // Halaman biasa: tepat satu <h1> (judul di header kerangka).
            // Halaman dokumen (.dokumen) boleh menambah judul sampul sendiri.
            $jumlahH1 = preg_match_all('#<h1[ >]#', $isi);
            $dokumen = str_contains($isi, 'class="dokumen"');
            if ($jumlahH1 < 1 || (! $dokumen && $jumlahH1 !== 1)) {
                $gagal[] = $u.' → '.$jumlahH1.' tag <h1> (halaman non-dokumen harus tepat 1 di header)';
            }
        }

        $this->assertSame([], $gagal,
            "Halaman berikut belum memakai kerangka layouts.app yang sama:\n".implode("\n", $gagal));
    }
}
