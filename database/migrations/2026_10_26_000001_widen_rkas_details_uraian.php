<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selaraskan panjang uraian Kertas Kerja dengan ARKAS 4.
 *
 * Form "Susun Kertas Kerja" sudah memakai `maxlength="500"` dan validasi
 * `max:500` (sama dengan batas uraian ARKAS 4), tetapi kolom
 * `rkas_details.uraian` masih VARCHAR(255). Akibatnya uraian 256–500 karakter
 * lolos validasi lalu terpotong (MySQL longgar) atau gagal simpan (PostgreSQL).
 *
 * Migrasi ini melebarkan kolom ke 500 karakter — tanpa mengubah nama/tipe data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->string('uraian', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rkas_details', function (Blueprint $t) {
            // Kembalikan ke 255; baris yang lebih panjang akan terpotong bila ada.
            $t->string('uraian', 255)->change();
        });
    }
};
