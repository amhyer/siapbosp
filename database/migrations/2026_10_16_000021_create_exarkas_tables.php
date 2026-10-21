<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul EXARKAS: master kode kegiatan ARKAS, daftar nota, volume,
 * dan alokasi volume per tahap — sumber laporan ala workbook EXARKAS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arkas_kodes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->string('kode', 20);
            $t->string('uraian');
            $t->tinyInteger('level');
            $t->timestamps();
            $t->unique(['school_id', 'tahun', 'kode']);
        });

        Schema::create('kas_notas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->string('arkas_id')->nullable();
            $t->string('no_bukti')->nullable();
            $t->date('tanggal_nota');
            $t->string('no_nota')->nullable();
            $t->string('nama_toko')->nullable();
            $t->string('alamat_toko')->nullable();
            $t->string('no_telp')->nullable();
            $t->string('npwp')->nullable();
            $t->bigInteger('total')->default(0);
            $t->bigInteger('lunas_bayar')->default(0);
            $t->timestamps();
            $t->unique(['school_id', 'arkas_id']);
            $t->index(['school_id', 'tahun']);
        });

        Schema::table('kas_transactions', function (Blueprint $t) {
            $t->string('arkas_nota_id')->nullable()->after('arkas_jenis_bku');
        });
        Schema::table('kas_details', function (Blueprint $t) {
            $t->decimal('volume', 16, 4)->nullable()->after('nilai');
            $t->string('satuan', 40)->nullable()->after('volume');
        });
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->text('periode_volume')->nullable()->after('kode_kegiatan');
        });
    }

    public function down(): void
    {
        Schema::table('rkas_details', fn (Blueprint $t) => $t->dropColumn('periode_volume'));
        Schema::table('kas_details', fn (Blueprint $t) => $t->dropColumn(['volume', 'satuan']));
        Schema::table('kas_transactions', fn (Blueprint $t) => $t->dropColumn('arkas_nota_id'));
        Schema::dropIfExists('kas_notas');
        Schema::dropIfExists('arkas_kodes');
    }
};
