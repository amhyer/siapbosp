<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkas_headers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->integer('revisi_ke')->default(1);
            $t->string('no_rkas')->nullable();
            $t->string('status')->default('draft'); // draft, diajukan, disetujui, dikunci
            $t->text('catatan')->nullable();
            $t->timestamps();
        });
        Schema::create('rkas_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rkas_header_id')->constrained('rkas_headers')->cascadeOnDelete();
            $t->foreignId('rekening_id')->constrained('rekenings')->cascadeOnDelete();
            $t->string('uraian');
            $t->string('satuan')->default('paket');
            $t->integer('banyak')->default(1);
            $t->bigInteger('harga_satuan');
            $t->bigInteger('jumlah');
            $t->string('sumber_dana')->default('BOS Reguler');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkas_details');
        Schema::dropIfExists('rkas_headers');
    }
};
