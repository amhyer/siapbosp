<?php

use App\Models\BosType;
use Illuminate\Database\Migrations\Migration;

/**
 * Jenis sumber dana baru: BOSP Afirmasi (ARKAS 4.2.16+, ref_sumber_dana
 * id 11 "BOS Afirmasi") dan "Lainnya" (id 5 — Banpem/dana lain).
 *
 * Idempoten: aman dijalankan berulang (firstOrCreate).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'afirmasi' => 'BOSP Afirmasi',
            'lainnya' => 'Sumber Dana Lainnya',
        ] as $kode => $nama) {
            BosType::firstOrCreate(['kode' => $kode], ['nama' => $nama]);
        }
    }

    public function down(): void
    {
        BosType::whereIn('kode', ['afirmasi', 'lainnya'])->delete();
    }
};
