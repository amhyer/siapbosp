<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kolom yang dicetak ARKAS pada lembar RKA/DPA Unit Kerja 2.2
 * ("Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan"):
 * `Lokasi Kegiatan` dan `Target Kinerja (Kuantitatif)`.
 *
 * Keduanya belum ada di `rkas_details`, sehingga lembar 2.2 sebelumnya tidak bisa
 * dibuat tanpa mengarang isi kolom. Sifatnya opsional (nullable): rincian lama
 * tetap sah, dan saat cetak dipakai nilai turunan bila kosong
 * (lokasi → nama sekolah, target → volume + satuan rincian), sama seperti yang
 * tercetak pada sampel ARKAS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->string('lokasi_kegiatan', 120)->nullable()->after('kode_kegiatan');
            $t->string('target_kinerja', 120)->nullable()->after('lokasi_kegiatan');
        });
    }

    public function down(): void
    {
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->dropColumn(['lokasi_kegiatan', 'target_kinerja']);
        });
    }
};
