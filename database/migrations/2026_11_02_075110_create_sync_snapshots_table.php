<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->date('snapshot_date');
            $table->unsignedBigInteger('total_pendapatan')->default(0);
            $table->unsignedBigInteger('total_belanja')->default(0);
            $table->bigInteger('saldo')->default(0);
            $table->unsignedInteger('total_transaksi')->default(0);
            $table->string('manifest_hash', 64);
            $table->json('payload_summary')->nullable();
            $table->boolean('is_frozen')->default(false);
            $table->dateTime('frozen_at')->nullable();
            $table->foreignId('frozen_by_lpj_id')->nullable()->constrained('lpj_submissions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'tahun', 'snapshot_date']);
            $table->index(['school_id', 'tahun', 'is_frozen']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_snapshots');
    }
};
