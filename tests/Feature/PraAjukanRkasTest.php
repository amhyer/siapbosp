<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\StandarHarga;
use App\Models\User;
use App\Services\PraAjukanRkasService;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerbang pra-pengajuan Kertas Kerja (P0-2) dan padanan kosakata status ARKAS 4 (P0-1).
 *
 * Rujukan: docs/MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md
 */
class PraAjukanRkasTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $operator;

    private User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterSeeder::class);

        $this->school = School::create([
            'npsn' => '40301002',
            'nama' => 'SDN Gerbang Makassar',
            'jenjang' => 'SD',
            'status_sekolah' => 'negeri',
            'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000,
            'nama_komite' => 'H. Syamsuddin, S.Pd',
            'email_komite' => 'komite@sdn.test',
            'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->operator = User::create([
            'name' => 'Bendahara BOSP',
            'email' => 'op2@sdn.test',
            'password' => 'secret123',
            'role' => 'operator',
            'school_id' => $this->school->id,
        ]);

        $this->kepsek = User::create([
            'name' => 'Kepala Sekolah',
            'email' => 'ks2@sdn.test',
            'password' => 'secret123',
            'role' => 'kepsek',
            'school_id' => $this->school->id,
        ]);

        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        Allocation::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id,
            'tahun' => 2026,
            'bos_type_id' => $bos->id,
            'nilai' => 100_000_000,
        ]);
    }

    /** Kertas Kerja patuh: Buku 15%, Honor 20%, ATK 65% dari pagu 100 juta. */
    private function kertasKerjaPatuh(): RkasHeader
    {
        return $this->buatKertasKerja([
            ['5.2.05.01.01.0001', 'Buku Perpustakaan', 15_000_000],
            ['5.1.02.02.01.0013', 'Honor Guru Non-ASN', 20_000_000],
            ['5.1.02.01.01.0024', 'ATK Operasional', 65_000_000],
        ]);
    }

    /**
     * @param  array<int,array{0:string,1:string,2:int}>  $baris  [kode rekening, uraian, jumlah]
     */
    private function buatKertasKerja(array $baris, string $status = 'draft', ?string $uraianPanjang = null): RkasHeader
    {
        $header = RkasHeader::create([
            'school_id' => $this->school->id,
            'tahun' => 2026,
            'revisi_ke' => 1,
            'status' => $status,
            'sumber_dana' => 'BOS Reguler',
            'jenis_rkas' => 'murni',
        ]);

        foreach ($baris as [$kode, $uraian, $jumlah]) {
            $rek = Rekening::where('kode', $kode)->firstOrFail();
            $header->details()->create([
                'rekening_id' => $rek->id,
                'kode_kegiatan' => '06.05',
                'uraian' => $uraianPanjang ?? $uraian,
                'satuan' => 'paket',
                'banyak' => 1,
                'harga_satuan' => $jumlah,
                'jumlah' => $jumlah,
                'sumber_dana' => 'BOS Reguler',
            ]);
        }

        return $header->fresh();
    }

    public function test_gerbang_menolak_pengajuan_saat_belum_dianggarkan_bukan_nol(): void
    {
        // Total 60 juta dari pagu 100 juta → Belum Dianggarkan Rp 40 juta.
        $h = $this->buatKertasKerja([
            ['5.1.02.01.01.0024', 'ATK Operasional', 60_000_000],
        ]);

        $hasil = PraAjukanRkasService::periksa($h, $this->school);
        $this->assertFalse($hasil['boleh_ajukan']);
        $this->assertSame(40_000_000, $hasil['belum_dianggarkan']);
        $kodeBlokir = collect($hasil['cek'])->where('level', 'blokir')->pluck('kode')->all();
        $this->assertContains('belum_dianggarkan', $kodeBlokir);

        $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->post(route('rkas.aksi', $h), ['aksi' => 'ajukan'])
            ->assertSessionHas('error');

        $this->assertSame('draft', $h->fresh()->status, 'Status tidak boleh berubah saat pengajuan ditolak.');
    }

    public function test_gerbang_meloloskan_kertas_kerja_patuh(): void
    {
        $h = $this->kertasKerjaPatuh();

        $hasil = PraAjukanRkasService::periksa($h, $this->school);
        $this->assertTrue($hasil['boleh_ajukan'], 'Kertas kerja patuh harus lolos: '.json_encode($hasil['cek']));
        $this->assertSame(0, $hasil['belum_dianggarkan']);

        $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->post(route('rkas.aksi', $h), ['aksi' => 'ajukan'])
            ->assertSessionHas('ok');

        $this->assertSame('diajukan', $h->fresh()->status);
    }

    public function test_uraian_sepanjang_batas_arkas_500_karakter_tersimpan_utuh(): void
    {
        // ARKAS 4 mengizinkan uraian sampai 500 karakter; kolom DB dulu 255 sehingga
        // isian 256–500 karakter terpotong (MySQL) atau gagal simpan (PostgreSQL).
        $h = $this->kertasKerjaPatuh();
        $uraian = str_pad('Kertas HVS A4 70 gsm untuk kegiatan pembelajaran', 480, ' x');

        $detail = $h->details()->first();
        $detail->update(['uraian' => $uraian]);

        $this->assertSame(480, mb_strlen($detail->fresh()->uraian));
    }

    public function test_gerbang_menolak_rincian_tanpa_volume_atau_harga(): void
    {
        $h = $this->kertasKerjaPatuh();
        $h->details()->first()->update(['banyak' => 0, 'harga_satuan' => 0, 'jumlah' => 0]);

        $hasil = PraAjukanRkasService::periksa($h->fresh(), $this->school);
        $this->assertFalse($hasil['boleh_ajukan']);
        $this->assertContains('rincian_tidak_lengkap',
            collect($hasil['cek'])->where('level', 'blokir')->pluck('kode')->all());
    }

    public function test_harga_di_atas_standar_hanya_peringatan_bukan_blokir(): void
    {
        $rek = Rekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();
        $ssh = StandarHarga::create([
            'school_id' => null,
            'kode_rekening' => $rek->kode,
            'nama_barang' => 'ATK paket',
            'satuan' => 'paket',
            'batas_harga' => 50_000_000,
            'kategori' => 'ssh',
        ]);

        $h = $this->kertasKerjaPatuh(); // ATK 65 juta > batas 50 juta
        $hasil = PraAjukanRkasService::periksa($h, $this->school);

        $this->assertTrue($hasil['boleh_ajukan'], 'Harga di atas standar tidak boleh memblokir pengajuan.');
        $this->assertGreaterThanOrEqual(1, $hasil['jumlah_peringatan']);
        $cek = collect($hasil['cek'])->firstWhere('kode', 'harga_di_atas_standar');
        $this->assertNotNull($cek);
        $this->assertSame('peringatan', $cek['level']);
        $this->assertNotNull($ssh->id);
    }

    public function test_endpoint_pra_ajukan_mengembalikan_json(): void
    {
        $h = $this->buatKertasKerja([['5.1.02.01.01.0024', 'ATK', 60_000_000]]);

        $res = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->getJson(route('rkas.pra-ajukan', $h));

        $res->assertOk()
            ->assertJsonStructure([
                'boleh_ajukan', 'jumlah_blokir', 'jumlah_peringatan',
                'pagu', 'sudah_dianggarkan', 'belum_dianggarkan',
                'cek' => [['kode', 'level', 'judul', 'pesan', 'baris']],
            ])
            ->assertJsonPath('boleh_ajukan', false)
            ->assertJsonPath('belum_dianggarkan', 40_000_000);
    }

    public function test_halaman_kertas_kerja_menampilkan_panel_kelayakan_dan_menonaktifkan_tombol(): void
    {
        $h = $this->buatKertasKerja([['5.1.02.01.01.0024', 'ATK', 60_000_000]]);

        $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get(route('rkas.show', $h))
            ->assertOk()
            ->assertSee('Kelayakan Pengajuan')
            ->assertSee('Belum Dianggarkan')
            ->assertSee('disabled', false);
    }

    public function test_daftar_kertas_kerja_menampilkan_chip_status_arkas(): void
    {
        $h = $this->kertasKerjaPatuh();
        $h->update(['status' => 'disetujui']);

        $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->get(route('rkas.index'))
            ->assertOk()
            ->assertSee('Disetujui Kepsek')
            ->assertSee('Siap Diajukan');
    }

    public function test_padanan_status_arkas_4(): void
    {
        $harapan = [
            'draft' => 'Draf',
            'diajukan' => 'Draf',
            'disetujui' => 'Siap Diajukan',
            'dikirim_dinas' => 'Menunggu Review Dinas',
            'revisi' => 'Perlu Revisi',
            'disahkan' => 'Disahkan',
            'dikunci' => 'Diarsipkan',
        ];

        foreach ($harapan as $status => $label) {
            $h = new RkasHeader(['status' => $status]);
            $this->assertSame($label, $h->labelStatusArkas(), "Status {$status} salah padanan");
            $this->assertArrayHasKey($h->nadaStatusArkas(), [
                'slate' => 1, 'sky' => 1, 'indigo' => 1, 'rose' => 1, 'emerald' => 1,
            ]);
        }

        // Istilah internal "Disetujui Kepsek" berbeda dari ARKAS "Siap Diajukan".
        $this->assertTrue((new RkasHeader(['status' => 'disetujui']))->statusArkasBerbeda());
        // "Disahkan Dinas" vs "Disahkan" tetap dianggap berbeda (chip kedua tampil).
        $this->assertTrue((new RkasHeader(['status' => 'disahkan']))->statusArkasBerbeda());
    }
}
