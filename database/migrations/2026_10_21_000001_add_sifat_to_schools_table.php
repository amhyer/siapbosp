<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sifat satuan pendidikan (negeri/swasta) — dipakai Cek Juknis 8/2026:
 * batas komponen honor 20% (negeri) / 40% (swasta) untuk jenjang dasar
 * dan menengah; PAUD & Kesetaraan 40% untuk keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $t) {
            $t->string('sifat')->default('negeri')->after('jenjang');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $t) {
            $t->dropColumn('sifat');
        });
    }
};
