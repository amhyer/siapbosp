<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bos_types', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('nama');
            $t->timestamps();
        });
        Schema::create('komponens', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('nama');
            $t->timestamps();
        });
        Schema::create('rekenings', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('nama');
            $t->string('tipe')->default('belanja'); // pendapatan | belanja
            $t->foreignId('komponen_id')->nullable()->constrained('komponens')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('rekening_pajaks', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('nama');
            $t->timestamps();
        });
        Schema::create('stages', function (Blueprint $t) {
            $t->id();
            $t->integer('tahun');
            $t->string('nama');
            $t->integer('urutan');
            $t->boolean('is_open')->default(true);
            $t->timestamps();
        });
        Schema::create('allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->foreignId('bos_type_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $t->bigInteger('nilai');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('rekening_pajaks');
        Schema::dropIfExists('rekenings');
        Schema::dropIfExists('komponens');
        Schema::dropIfExists('bos_types');
    }
};
