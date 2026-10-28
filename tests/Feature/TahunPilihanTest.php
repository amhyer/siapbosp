<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemilih tahun anggaran tidak boleh dipaku ke rentang 2022–2028.
 *
 * Halaman Arsip dan Dashboard sudah lebih dulu memakai rentang dinamis; tiga
 * formulir lain (impor ARKAS, cek integritas, rekonsiliasi) masih memakai
 * @for($y = 2022; $y <= 2028; $y++), sehingga sekolah dengan berkas 2021 atau
 * tahun 2029 tidak bisa memilih tahun yang benar. Validasi servernya sendiri
 * sudah menerima 2020–2030.
 */
class TahunPilihanTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();

        $s = School::create(['npsn' => '90000202', 'nama' => 'SD Tahun', 'jenjang' => 'SD',
            'kecamatan' => 'X', 'kab_kota' => 'Y', 'provinsi' => 'Z']);
        $this->op = User::create(['name' => 'Op Tahun', 'email' => 'tahun@t.t', 'password' => 'p',
            'role' => 'operator', 'school_id' => $s->id]);

        $this->actingAs($this->op);
    }

    /**
     * Ketiga formulir mengirim tahun anggaran yang sama; PHPUnit 12 tidak lagi
     * mendukung @dataProvider gaya docblock, jadi rute diulang di dalam test.
     */
    public function test_tahun_di_luar_rentang_lama_tetap_bisa_dipilih(): void
    {
        $rute = ['impor-arkas.form', 'impor-arkas.cek-integritas', 'rekonsiliasi.form'];

        foreach ($rute as $namaRute) {
            foreach ([2021, 2029] as $tahun) {
                $html = $this->withSession(['tahun' => $tahun])
                    ->get(route($namaRute))
                    ->assertOk()
                    ->getContent();

                $this->assertStringContainsString('<option value="'.$tahun.'" selected', $html,
                    'Tahun '.$tahun.' tidak muncul/terpilih di '.$namaRute.'.');
            }
        }
    }

    public function test_rentang_bawaan_tetap_lengkap_2022_sampai_2028(): void
    {
        $html = $this->withSession(['tahun' => 2026])
            ->get(route('rekonsiliasi.form'))
            ->assertOk()
            ->getContent();

        foreach (range(2022, 2028) as $tahun) {
            $this->assertStringContainsString('<option value="'.$tahun.'"', $html);
        }
    }
}
