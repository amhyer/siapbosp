<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->string('type'); // pendapatan, ambil_simpanan, mutasi_tunai_ke_bank, belanja, setor_pajak, setor_utang_pajak
            $t->integer('no_bku')->nullable();
            $t->string('no_bukti')->nullable();
            $t->date('tanggal');
            $t->foreignId('bos_type_id')->nullable()->constrained('bos_types')->nullOnDelete();
            $t->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $t->string('metode_bayar')->nullable(); // tunai | transfer
            $t->string('keterangan');
            $t->bigInteger('total');
            $t->foreignId('belanja_transaction_id')->nullable()->constrained('kas_transactions')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('kas_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kas_transaction_id')->constrained('kas_transactions')->cascadeOnDelete();
            $t->foreignId('rekening_id')->nullable()->constrained('rekenings')->nullOnDelete();
            $t->string('uraian')->nullable();
            $t->bigInteger('nilai');
            $t->timestamps();
        });
        Schema::create('kas_taxes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kas_transaction_id')->constrained('kas_transactions')->cascadeOnDelete();
            $t->foreignId('rekening_pajak_id')->constrained('rekening_pajaks')->cascadeOnDelete();
            $t->string('kode_billing')->nullable();
            $t->string('ntpn')->nullable();
            $t->bigInteger('nilai');
            $t->string('status')->default('belum'); // belum | sudah
            $t->foreignId('paid_transaction_id')->nullable()->constrained('kas_transactions')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_taxes');
        Schema::dropIfExists('kas_details');
        Schema::dropIfExists('kas_transactions');
    }
};
