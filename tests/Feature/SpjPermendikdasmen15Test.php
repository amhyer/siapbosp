<?php

namespace Tests\Feature;

use App\Models\BosType;
use App\Models\KasNota;
use App\Models\KasTransaction;
use App\Models\Penandatangan;
use App\Models\Rekening;
use App\Models\School;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paket Dokumen SPJ — kini mengikuti Permendikdasmen No 15/2026:
 * 6 bukti pelaporan (Pasal 24) + ambang dokumen perencanaan Rp1 juta
 * (Pasal 12 ayat 4).
 */
class SpjPermendikdasmen15Test extends TestCase
{
    use RefreshDatabase;

    private School $sekolah;

    private User $operator;

    private KasTransaction $belanja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::create([
            'npsn' => '90000007', 'nama' => 'SD Negeri Uji SPJ', 'jenjang' => 'SD',
            'sifat' => 'negeri', 'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar',
        ]);
        $this->operator = User::create([
            'name' => 'Operator SPJ', 'email' => 'spj@tes.test', 'password' => 'password',
            'role' => 'operator', 'school_id' => $this->sekolah->id,
        ]);
        $bos = BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        $stage = Stage::create(['tahun' => 2026, 'nama' => 'Tahap 1', 'urutan' => 1, 'is_open' => true]);
        $rek = Rekening::firstOrCreate(['kode' => '5.1.02.01.01.0001'], ['nama' => 'ATK', 'tipe' => 'belanja']);

        $nota = KasNota::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2026, 'tanggal_nota' => '2026-03-07',
            'no_bukti' => 'BKT-001/KAS/III/2026', 'total' => 3000000,
            'nama_toko' => 'Toko Uji', 'alamat_toko' => 'Jl. Uji 1', 'npwp' => '09.000.000.1-000.000',
        ]);

        $this->belanja = KasTransaction::create([
            'school_id' => $this->sekolah->id, 'tahun' => 2026, 'type' => 'belanja',
            'no_bku' => 1, 'no_bukti' => 'BKT-001/KAS/III/2026', 'tanggal' => '2026-03-07',
            'bos_type_id' => $bos->id, 'stage_id' => $stage->id, 'metode_bayar' => 'tunai',
            'keterangan' => 'Pengadaan ATK', 'total' => 3000000, 'created_by' => $this->operator->id,
        ]);
        $this->belanja->details()->create([
            'rekening_id' => $rek->id, 'uraian' => 'Pengadaan ATK', 'nilai' => 3000000,
        ]);
        Penandatangan::create([
            'school_id' => $this->sekolah->id, 'nama' => 'Hj. Kepala Sekolah', 'nip' => '198001012000001',
            'jabatan' => 'Kepala Sekolah',
        ]);
    }

    public function test_paket_spj_memuat_ringkasan_kepatuhan_2026(): void
    {
        $this->actingAs($this->operator)
            ->get('/pengeluaran/belanja/'.$this->belanja->id.'/spj')
            ->assertOk()
            ->assertSee('RINGKASAN KEPATUHAN BELANJA BARANG/JASA')
            ->assertSee('Permendikdasmen No 15 Tahun 2026')
            ->assertSee('Dokumen Perencanaan (KAK/RKA)')
            ->assertSee('Bukti Perbandingan Harga')
            ->assertSee('WAJIB');
    }

    public function test_belanja_di_bawah_1_juta_dikecualikan_dari_dokumen_perencanaan(): void
    {
        $this->belanja->update(['total' => 900000]);
        $this->belanja->details()->update(['nilai' => 900000]);

        $this->actingAs($this->operator)
            ->get('/pengeluaran/belanja/'.$this->belanja->id.'/spj')
            ->assertOk()
            ->assertSee('DIKECUALIKAN')
            ->assertSee('Rp1.000.000');
    }
}
