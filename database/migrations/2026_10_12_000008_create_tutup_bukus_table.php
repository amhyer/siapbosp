<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutup_bukus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $t->string('ditutup_oleh')->nullable();
            $t->timestamps();
            $t->unique(['school_id', 'tahun', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutup_bukus');
    }
};
