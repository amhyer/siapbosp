<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Jejak audit ringan: siapa melakukan apa, dari sekolah mana — dipantau Dinas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('user_name')->default('Sistem');
            $t->string('aksi', 40);            // transaksi, rkas, tutup_buku, buka_buku, pengumuman
            $t->text('keterangan')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['aksi', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
