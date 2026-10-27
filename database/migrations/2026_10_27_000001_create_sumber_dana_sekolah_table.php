<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Daftar sumber dana milik sekolah, diimpor apa adanya dari master
// `ref_sumber_dana` di arkas.db (sheet Ref_Sumber_Dana). Dipakai tab
// "Sumber Dana Tambahan" di Penganggaran: chip nama sumber + penyaring,
// tanpa layanan pusat — lihat docs/UJI-ARKAS-LAPTOP-SUMBER-DANA.md §6.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumber_dana_sekolah', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Id ARKAS boleh kosong bila kolomnya tidak ada di versi ARKAS lain;
            // baris dengan id kosong dipertahankan lewat kunci nama (lihat ArkasImportService).
            $t->string('id_ref_sumber_dana', 30)->nullable()->after('school_id');
            $t->string('kode', 40)->nullable();
            $t->string('nama_sumber_dana', 200);
            $t->string('alias', 200)->nullable();
            $t->boolean('is_sumber_dana_tambahan')->default(false);
            $t->timestamps();

            $t->unique(['school_id', 'id_ref_sumber_dana'], 'sumber_dana_sekolah_school_arkas_unique');
            $t->index(['school_id', 'nama_sumber_dana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumber_dana_sekolah');
    }
};
