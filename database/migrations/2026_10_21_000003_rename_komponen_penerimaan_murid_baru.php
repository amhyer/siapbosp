<?php

use App\Models\Komponen;
use Illuminate\Database\Migrations\Migration;

/**
 * Permendikdasmen No 8/2026: komponen "Penerimaan Peserta Didik Baru"
 * disebut "Penerimaan Murid Baru" (dijelaskan pada Komponen Penggunaan
 * Dana BOS Reguler, halaman 32).
 */
return new class extends Migration
{
    public function up(): void
    {
        Komponen::where('kode', '01')->update(['nama' => 'Pelaksanaan Penerimaan Murid Baru']);
    }

    public function down(): void
    {
        Komponen::where('kode', '01')->update(['nama' => 'Pelaksanaan Penerimaan Peserta Didik Baru']);
    }
};
