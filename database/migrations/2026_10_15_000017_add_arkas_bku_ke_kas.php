<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kas_transactions', function (Blueprint $t) {
            $t->string('arkas_bku')->nullable()->after('no_bukti');
            $t->string('arkas_jenis_bku')->nullable()->after('arkas_bku');
        });
        // Backfill: baris impor lama sudah menyimpan no_bukti ARKAS.
        DB::table('kas_transactions')->whereNull('arkas_bku')
            ->whereNotNull('no_bukti')->update(['arkas_bku' => DB::raw('no_bukti')]);
    }

    public function down(): void
    {
        Schema::table('kas_transactions', function (Blueprint $t) {
            $t->dropColumn(['arkas_bku', 'arkas_jenis_bku']);
        });
    }
};
