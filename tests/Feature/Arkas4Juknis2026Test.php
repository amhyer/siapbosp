<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BosType;
use App\Models\KasTransaction;
use App\Models\Rekening;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\Stage;
use App\Models\StandarHarga;
use App\Models\User;
use App\Services\JuknisBosp2026Service;
use Carbon\Carbon;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Arkas4Juknis2026Test extends TestCase
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
            'npsn' => '40301001',
            'nama' => 'SDN Percontohan Makassar',
            'jenjang' => 'SD',
            'status_sekolah' => 'negeri',
            'jumlah_siswa' => 100,
            'satuan_biaya_bosp' => 1_000_000,
            'nama_komite' => 'H. Syamsuddin, S.Pd',
            'email_komite' => 'komite@sdnpercontohan.sch.id',
            'kab_kota' => 'Kota Makassar',
            'provinsi' => 'Sulawesi Selatan',
        ]);

        $this->operator = User::create([
            'name' => 'Bendahara BOSP',
            'email' => 'op@sdn.test',
            'password' => 'secret123',
            'role' => 'operator',
            'school_id' => $this->school->id,
        ]);

        $this->kepsek = User::create([
            'name' => 'Kepala Sekolah',
            'email' => 'ks@sdn.test',
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

    public function test_aturan_proporsi_permendikdasmen_8_tahun_2026_semua_jenjang(): void
    {
        // 1. SD Negeri (Pasal 42): Buku >= 10%, Sarpras <= 20%, Honor <= 20%
        $sdNegeri = JuknisBosp2026Service::batasProporsi($this->school, 'BOS Reguler');
        $this->assertSame(10.0, $sdNegeri['buku_min_pct']);
        $this->assertSame(20.0, $sdNegeri['sarpras_max_pct']);
        $this->assertSame(20.0, $sdNegeri['honor_max_pct']);

        // 2. SD Swasta (Pasal 42): Honor <= 40%
        $swasta = new School(['jenjang' => 'SMP', 'status_sekolah' => 'swasta']);
        $smpSwasta = JuknisBosp2026Service::batasProporsi($swasta, 'BOS Reguler');
        $this->assertSame(10.0, $smpSwasta['buku_min_pct']);
        $this->assertSame(40.0, $smpSwasta['honor_max_pct']);

        // 3. PAUD (Pasal 38): Buku >= 5%, Sarpras <= 20%, Honor <= 40%
        $paud = new School(['jenjang' => 'PAUD', 'status_sekolah' => 'negeri']);
        $aturanPaud = JuknisBosp2026Service::batasProporsi($paud, 'BOS Reguler');
        $this->assertSame(5.0, $aturanPaud['buku_min_pct']);
        $this->assertSame(40.0, $aturanPaud['honor_max_pct']);

        // 4. Kesetaraan (Pasal 48): Buku >= 10%, Sarpras <= 20%, Honor <= 40%
        $pkbm = new School(['jenjang' => 'Kesetaraan', 'status_sekolah' => 'swasta']);
        $aturanPkbm = JuknisBosp2026Service::batasProporsi($pkbm, 'BOS Reguler');
        $this->assertSame(10.0, $aturanPkbm['buku_min_pct']);
        $this->assertSame(40.0, $aturanPkbm['honor_max_pct']);

        // 5. BOSP Kinerja (ARKAS v4.2.19): mode antar_jenis tanpa batas honor reguler
        $kinerja = JuknisBosp2026Service::batasProporsi($this->school, 'BOS Kinerja');
        $this->assertSame('antar_jenis', $kinerja['mode']);
        $this->assertNull($kinerja['honor_max_pct']);
    }

    public function test_alur_hulu_pbd_rkm_rkt_dorong_ke_rkas_dan_batas_ssh(): void
    {
        $rekBuku = Rekening::where('kode', 'like', '5.2.05%')->firstOrFail();

        $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->post(route('perencanaan.pbd.template'))
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->post(route('perencanaan.rkt.store'), [
                'kode_kegiatan' => '02.02',
                'nama_kegiatan' => 'Pengadaan Buku Teks Utama Kurikulum Nasional',
                'butuh_anggaran' => 1,
                'rekening_id' => $rekBuku->id,
                'sumber_dana' => 'BOS Reguler',
                'volume' => 100,
                'satuan' => 'eksemplar',
                'harga_satuan' => 150_000,
                'bulan_target' => 2,
            ])->assertRedirect();

        $resp = $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->post(route('perencanaan.rkt.keRkas'));

        $rkas = RkasHeader::where('school_id', $this->school->id)->where('tahun', 2026)->first();
        $this->assertNotNull($rkas);
        $resp->assertRedirect(route('rkas.show', $rkas));
        $this->assertSame(15_000_000, (int) $rkas->details()->sum('jumlah'));
        $this->assertSame(15_000_000, $rkas->details->first()->nominalTahap1());

        // Uji proteksi batas atas Standar Harga (SSH Dinas)
        $sshHvs = StandarHarga::where('nama_barang', 'like', 'Kertas HVS%')->firstOrFail();
        $rekAtk = Rekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();

        $this->actingAs($this->operator)
            ->withSession(['tahun' => 2026])
            ->post(route('rkas.store'), [
                'tahun' => 2026,
                'rekening_id' => [$rekAtk->id],
                'standar_harga_id' => [$sshHvs->id],
                'uraian' => ['Kertas HVS Overprice'],
                'satuan' => ['rim'],
                'banyak' => [10],
                'harga' => [$sshHvs->batas_harga + 10_000],
            ])->assertSessionHas('error');
    }

    public function test_kertas_kerja_blokir_honor_berlebih_negeri_dan_pergeseran_vs_perubahan(): void
    {
        $rekHonor = Rekening::where('kode', '5.1.02.02.01.0013')->firstOrFail();
        $rekBuku = Rekening::where('kode', 'like', '5.2.05%')->firstOrFail();
        $rekAtk = Rekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();

        // 1. Honor 30% di SD Negeri (melanggar batas Pasal 42 maks 20%) ditahan saat Kirim ke Dinas
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])->post(route('rkas.store'), [
            'tahun' => 2026,
            'jenis_rkas' => 'murni',
            'sumber_dana' => 'BOS Reguler',
            'rekening_id' => [$rekHonor->id, $rekBuku->id, $rekAtk->id],
            'uraian' => ['Honor Guru Non-ASN', 'Buku Perpustakaan', 'ATK Operasional'],
            'satuan' => ['OB', 'paket', 'paket'],
            'harga' => [30_000_000, 15_000_000, 55_000_000],
            'bulan' => [
                0 => [1 => 1],
                1 => [2 => 1],
                2 => [8 => 1],
            ],
        ])->assertRedirect();

        $rkasMelanggar = RkasHeader::latest('id')->firstOrFail();
        $this->actingAs($this->operator)->post(route('rkas.aksi', $rkasMelanggar), ['aksi' => 'ajukan']);
        $this->actingAs($this->kepsek)
            ->withSession(['password_hash_web' => (string) $this->kepsek->getAuthPassword()])
            ->post(route('rkas.aksi', $rkasMelanggar), ['aksi' => 'setujui']);
        $this->actingAs($this->operator)
            ->withSession(['password_hash_web' => (string) $this->operator->getAuthPassword()])
            ->post(route('rkas.aksi', $rkasMelanggar), ['aksi' => 'kirim_dinas'])
            ->assertSessionHas('error');

        // 2. Kertas Kerja patuh Juknis 2026 (Honor 20%, Buku 15%, ATK 65%, Tahap 1 = 35% <= 50%, Total = 100jt) lolos ke Dinas!
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])->post(route('rkas.store'), [
            'tahun' => 2026,
            'jenis_rkas' => 'murni',
            'sumber_dana' => 'BOS Reguler',
            'rekening_id' => [$rekHonor->id, $rekBuku->id, $rekAtk->id],
            'uraian' => ['Honor Guru Non-ASN', 'Buku Perpustakaan', 'ATK Operasional'],
            'satuan' => ['OB', 'paket', 'paket'],
            'harga' => [20_000_000, 15_000_000, 65_000_000],
            'bulan' => [
                0 => [1 => 1],
                1 => [3 => 1],
                2 => [8 => 1],
            ],
        ])->assertRedirect();

        $rkasPatuh = RkasHeader::latest('id')->firstOrFail();
        $this->actingAs($this->operator)->post(route('rkas.aksi', $rkasPatuh), ['aksi' => 'ajukan']);
        $this->actingAs($this->kepsek)
            ->withSession(['password_hash_web' => (string) $this->kepsek->getAuthPassword()])
            ->post(route('rkas.aksi', $rkasPatuh), ['aksi' => 'setujui']);
        $this->actingAs($this->operator)
            ->withSession(['password_hash_web' => (string) $this->operator->getAuthPassword()])
            ->post(route('rkas.aksi', $rkasPatuh), ['aksi' => 'kirim_dinas'])
            ->assertSessionHas('ok');
        $this->assertSame('dikirim_dinas', $rkasPatuh->fresh()->status);

        // Mode Salin Cepat ke ARKAS 4 Desktop + Toggle Checklist
        $detPertama = $rkasPatuh->details()->first();
        $this->actingAs($this->operator)->get(route('rkas.salinArkas', $rkasPatuh))->assertOk()->assertSee('Panduan Input Cepat ke ARKAS 4 Desktop');
        $this->actingAs($this->operator)->post(route('rkas.toggleArkas', [$rkasPatuh, $detPertama]))->assertRedirect();
        $this->assertTrue($detPertama->fresh()->sudah_diinput_arkas);

        // 3. Pergeseran lintas kelompok akun (menggeser 5.1.02 ke 5.2.05) WAJIB DITOLAK, tetapi Perubahan DITERIMA!
        $payloadLintasAkun = [
            'tahun' => 2026,
            'acuan_header_id' => $rkasPatuh->id,
            'sumber_dana' => 'BOS Reguler',
            'rekening_id' => [$rekHonor->id, $rekBuku->id, $rekAtk->id],
            'uraian' => ['Honor Guru Non-ASN', 'Buku Perpustakaan Tambahan', 'ATK Operasional'],
            'satuan' => ['OB', 'paket', 'paket'],
            'harga' => [20_000_000, 25_000_000, 55_000_000],
            'bulan' => [0 => [1 => 1], 1 => [3 => 1], 2 => [8 => 1]],
        ];

        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->post(route('rkas.store'), $payloadLintasAkun + ['jenis_rkas' => 'pergeseran'])
            ->assertSessionHas('error');

        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->post(route('rkas.store'), $payloadLintasAkun + ['jenis_rkas' => 'perubahan'])
            ->assertSessionHas('ok');
    }

    public function test_kalkulator_pajak_bku_kronologis_bunga_watermark_draft_dan_sptjm(): void
    {
        $bos = BosType::where('kode', 'reguler')->firstOrFail();
        $stage = Stage::where('tahun', 2026)->orderBy('urutan')->firstOrFail();
        $rekAtk = Rekening::where('kode', '5.1.02.01.01.0024')->firstOrFail();
        $rekMamin = Rekening::where('kode', 'like', '5.1.02.01.01.0052%')->first() ?? $rekAtk;

        // 1. Uji kalkulator pajak ARKAS 4: SIPLah vs Non-SIPLah > Rp2jt
        $jsonSiplah = $this->actingAs($this->operator)
            ->getJson(route('belanja.hitungPajak', ['sumber' => 'siplah', 'nilai' => 5_000_000, 'rekening_id' => $rekAtk->id]))
            ->assertOk()
            ->json();
        $this->assertTrue($jsonSiplah['otomatis_marketplace']);
        $this->assertCount(0, $jsonSiplah['items']);

        $jsonNonSiplah = $this->actingAs($this->operator)
            ->getJson(route('belanja.hitungPajak', ['sumber' => 'langsung', 'nilai' => 3_000_000, 'rekening_id' => $rekAtk->id]))
            ->assertOk()
            ->json();
        $this->assertFalse($jsonNonSiplah['otomatis_marketplace']);
        $this->assertGreaterThanOrEqual(2, count($jsonNonSiplah['items'])); // PPN + PPh 22

        // 2. Catat penerimaan dana pertama pada Maret 2026 (Jan & Feb otomatis tanpa dana) + belanja April 2026
        KasTransaction::create([
            'school_id' => $this->school->id,
            'tahun' => 2026,
            'type' => 'pendapatan',
            'no_bku' => 1,
            'tanggal' => '2026-03-05',
            'bos_type_id' => $bos->id,
            'stage_id' => $stage->id,
            'metode_bayar' => 'transfer',
            'keterangan' => 'Salur BOSP Reguler Tahap I',
            'total' => 50_000_000,
        ]);

        $this->actingAs($this->operator)->withSession(['tahun' => 2026])->post(route('belanja.store'), [
            'no_bukti' => 'BNU01',
            'tanggal' => '2026-04-10',
            'bos_type_id' => $bos->id,
            'stage_id' => $stage->id,
            'metode_bayar' => 'transfer',
            'sumber' => 'siplah',
            'nama_toko' => 'CV Mitra Pendidikan',
            'ongkir_siplah' => 50_000,
            'keterangan' => 'Belanja ATK SIPLah',
            'rekening_id' => [$rekMamin->id],
            'nilai' => [5_000_000],
        ])->assertRedirect(route('belanja.index'));

        // Cetak BKU Maret sebelum ditutup menampilkan watermark DRAFT
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('bku.cetak', ['bulan' => '2026-03']))
            ->assertOk()
            ->assertSee('Watermark DRAFT aktif');

        // Menutup April langsung sebelum Maret ditutup WAJIB DITOLAK (aturan kronologis ARKAS 4)
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->post(route('bku.tutup'), ['bulan' => '2026-04'])
            ->assertSessionHas('error');

        // Tutup Maret lengkap dengan Bunga Bank Rp25.000 & Pajak/Admin Bank Rp5.000 BERHASIL!
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->post(route('bku.tutup'), [
                'bulan' => '2026-03',
                'bunga_bank' => 25_000,
                'pajak_bunga' => 5_000,
            ])->assertRedirect(route('bku', ['bulan' => '2026-03']));

        // Setelah ditutup, watermark DRAFT pada BKU Maret hilang
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('bku.cetak', ['bulan' => '2026-03']))
            ->assertOk()
            ->assertDontSee('Watermark DRAFT aktif');

        // Kini April bisa ditutup berurutan
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->post(route('bku.tutup'), ['bulan' => '2026-04'])
            ->assertRedirect(route('bku', ['bulan' => '2026-04']));

        // 3. Uji cetak SPTJM Semester 1 & Peringatan Tenggat Pasal 59 (Oktober 2026 > 31 Juli 2026)
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->get(route('pembukuan.sptjm', ['semester' => 1]))
            ->assertOk()
            ->assertSee('SURAT PERNYATAAN TANGGUNG JAWAB MUTLAK (SPTJM)')
            ->assertSee('Rp 50.025.000')
            ->assertSee('Rp 5.005.000')
            ->assertSee('Rp 45.020.000');

        Carbon::setTestNow('2026-08-15');
        $tenggat = JuknisBosp2026Service::statusTenggatPelaporan($this->school->id, 2026);
        $this->assertTrue($tenggat['tahap1_terlambat']);
        $this->assertSame(2, $tenggat['potongan_pct']);
        Carbon::setTestNow();
    }

    public function test_seluruh_halaman_ui_arkas4_terender_tanpa_error_200_ok(): void
    {
        $rekBuku = Rekening::where('kode', 'like', '5.2.05%')->firstOrFail();
        $rkas2025 = RkasHeader::create([
            'school_id' => $this->school->id,
            'tahun' => 2025,
            'revisi_ke' => 1,
            'sumber_dana' => 'BOS Reguler',
            'jenis_rkas' => 'murni',
            'status' => 'disahkan',
        ]);
        $rkas2025->details()->create([
            'rekening_id' => $rekBuku->id,
            'kode_kegiatan' => '02.02',
            'uraian' => 'Buku Teks Utama',
            'satuan' => 'eks',
            'banyak' => 100,
            'harga_satuan' => 100_000,
            'jumlah' => 10_000_000,
            'sumber_dana' => 'BOS Reguler',
        ]);

        // Salin dari Tahun Lalu (2025 -> 2026) + Sisip Uraian
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->post(route('rkas.salin'))
            ->assertRedirect();

        $rkas2026 = RkasHeader::where('school_id', $this->school->id)->where('tahun', 2026)->firstOrFail();
        $det = $rkas2026->details()->firstOrFail();

        $this->actingAs($this->operator)->withSession(['tahun' => 2026])
            ->post(route('rkas.sisip', [$rkas2026, $det]), [
                'uraian' => 'Buku Pengayaan Literasi (Sisipan)',
                'banyak' => 50,
                'harga_satuan' => 80_000,
                'bulan' => 4,
            ])->assertRedirect();
        $this->assertSame(2, $rkas2026->details()->count());

        // Uji render 200 OK seluruh halaman baru & yang ditingkatkan
        foreach ([
            route('dashboard'),
            route('perencanaan.index'),
            route('perencanaan.ssh'),
            route('rkas.index'),
            route('rkas.create'),
            route('rkas.create', ['jenis' => 'pergeseran', 'acuan' => $rkas2026->id]),
            route('rkas.create', ['jenis' => 'perubahan', 'acuan' => $rkas2026->id]),
            route('rkas.show', $rkas2026),
            route('rkas.salinArkas', $rkas2026),
            route('belanja.create'),
            route('bku'),
            route('pembukuan.sptjm'),
            route('profil.edit'),
        ] as $url) {
            $this->actingAs($this->operator)->withSession(['tahun' => 2026])->get($url)->assertOk();
        }

        // Update profil lengkap parameter ARKAS 4 + sinkron pagu siswa
        $this->actingAs($this->operator)->withSession(['tahun' => 2026])->put(route('profil.update'), [
            'nama' => 'SDN Percontohan Makassar',
            'jenjang' => 'PAUD',
            'status_sekolah' => 'swasta',
            'jumlah_siswa' => 120,
            'satuan_biaya_bosp' => 600_000,
            'nama_komite' => 'Dra. Hj. Nurlina',
            'email_komite' => 'komite.paud@sekolah.sch.id',
            'akhir_jabatan_bulan' => 9,
            'sinkron_pagu' => 1,
        ])->assertRedirect();

        $this->assertSame('PAUD', $this->school->fresh()->jenjang);
        $this->assertSame('swasta', $this->school->fresh()->status_sekolah);
        $this->assertSame(72_000_000, $this->school->fresh()->estimasiPaguReguler());
    }
}
