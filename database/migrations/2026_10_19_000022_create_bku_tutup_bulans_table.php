<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N — BKU per bulan (ala ARKAS 4),
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bku_tutup_bulans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained();
            $t->integer('tahun');
            $t->string('bulan', 7); // '2026-03'
            $t->string('ditutup_oleh')->nullable();
            $t->timestamps();
            $t->unique(['school_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bku_tutup_bulans');
    }
};
