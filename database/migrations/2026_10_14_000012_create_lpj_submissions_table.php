<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Alur pertanggungjawaban: sekolah mengirim LPJ per tahap, Dinas meninjau (terima/tolak).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lpj_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $t->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $t->integer('tahun');
            $t->string('status', 20)->default('dikirim'); // dikirim, diterima, ditolak
            $t->text('catatan_dinas')->nullable();
            $t->string('dikirim_oleh')->nullable();
            $t->string('ditinjau_oleh')->nullable();
            $t->timestamp('ditinjau_at')->nullable();
            $t->timestamps();
            $t->index(['school_id', 'tahun', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpj_submissions');
    }
};
