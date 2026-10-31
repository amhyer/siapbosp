<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanFormatArkasTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_format_laporan_dapat_diakses_oleh_pengguna_sekolah(): void
    {
        $school = School::create([
            'npsn' => '12345678',
            'nama' => 'SD Negeri Contoh',
            'jenjang' => 'SD',
            'status_sekolah' => 'NEGERI',
        ]);

        $user = User::create([
            'name' => 'Operator Sekolah',
            'email' => 'operator@sekolah.demo',
            'password' => 'secret123',
            'role' => 'operator',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tahun' => 2026])
            ->get(route('laporan.format'));

        $response->assertOk();
        $response->assertSee('Format');
    }
}
