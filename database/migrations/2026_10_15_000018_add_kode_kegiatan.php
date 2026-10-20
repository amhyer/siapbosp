<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->string('kode_kegiatan', 20)->nullable()->after('rekening_id');
        });
        Schema::table('kas_details', function (Blueprint $t) {
            $t->string('kode_kegiatan', 20)->nullable()->after('rekening_id');
        });
    }

    public function down(): void
    {
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->dropColumn('kode_kegiatan');
        });
        Schema::table('kas_details', function (Blueprint $t) {
            $t->dropColumn('kode_kegiatan');
        });
    }
};
