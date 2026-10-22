<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master tambahan hasil impor ARKAS & untuk arsip:
 * - `satuans`  : master satuan belanja (ARKAS `ref_satuan`).
 * - `ptks`     : data edu/guru & tenaga kependidikan (ARKAS `ptk`).
 * - `uraian`   : master uraian belanja (bekal uraian dari RKAS impor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satuans', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 80)->unique();
            $t->string('unit', 80)->nullable();
            $t->timestamps();
        });

        Schema::create('ptks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->string('nama');
            $t->string('jenis_kelamin', 4)->nullable();
            $t->integer('masa_kerja_tahun')->default(0);
            $t->integer('masa_kerja_bulan')->default(0);
            $t->string('nuptk', 40)->nullable();
            $t->integer('jenis_ptk')->nullable();
            $t->boolean('pernah_sertifikasi')->default(false);
            $t->boolean('is_cut_off')->default(false);
            $t->string('arkas_id')->nullable();
            $t->timestamps();
            $t->unique(['school_id', 'arkas_id']);
            $t->index(['school_id', 'tahun']);
        });

        Schema::create('uraian', function (Blueprint $t) {
            $t->id();
            $t->string('uraian', 255)->unique();
            $t->foreignId('satuan_id')->nullable()->constrained('satuans')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uraian');
        Schema::dropIfExists('ptks');
        Schema::dropIfExists('satuans');
    }
};
