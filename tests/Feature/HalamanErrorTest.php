<?php

namespace Tests\Feature;

use Tests\TestCase;

class HalamanErrorTest extends TestCase
{
    public function test_respons_404_memakai_halaman_error_yang_ramah_pengguna(): void
    {
        $this->get('/__halaman-tidak-ada__')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Ke halaman masuk');
    }

    public function test_halaman_error_500_tidak_menampilkan_detail_teknis(): void
    {
        $this->view('errors.500')
            ->assertSee('Terjadi gangguan')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('stack trace');
    }
}
