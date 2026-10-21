<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Meng-hash token API yang tersimpan plaintext menjadi sha256.
 * Nilai asli token TIDAK berubah — pengguna tetap memakai token lamanya,
 * hanya cara penyimpanan yang berubah (lookup selalu hash(token)).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('api_token')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $u) {
                // Lewati nilai yang sudah berupa hash sha256 (64 hex) agar tidak dobel-hash.
                if (preg_match('/^[0-9a-f]{64}$/', $u->api_token)) {
                    continue;
                }
                DB::table('users')->where('id', $u->id)->update(['api_token' => hash('sha256', $u->api_token)]);
            }
        });
    }

    public function down(): void
    {
        // Hash bersifat satu arah — token plaintext lama tidak dapat dipulihkan.
        // Pengguna perlu membuat ulang token (Buat Ulang Token) setelah rollback.
    }
};
