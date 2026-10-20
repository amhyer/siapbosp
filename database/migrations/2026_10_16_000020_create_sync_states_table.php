<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status sinkronisasi sisi lokal (Tahap C). Di pusat tabel ini tidak dipakai.
 * kunci: terakhir_dorong (ISO datetime), versi_master (string).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $t) {
            $t->id();
            $t->string('kunci')->unique();
            $t->string('nilai')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
