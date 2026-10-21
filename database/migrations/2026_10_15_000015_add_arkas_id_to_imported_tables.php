<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kunci idempoten impor ARKAS. Catatan skema: hanya kas_transactions yang
// punya school_id; rkas_details terikat via rkas_header_id (header milik
// satu sekolah+tahun) dan kas_taxes via kas_transaction_id — jadi unik
// per-sekolah ditegakkan lewat induk masing-masing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kas_transactions', function (Blueprint $t) {
            $t->string('arkas_id')->nullable()->after('id');
            $t->unique(['school_id', 'arkas_id'], 'kas_transactions_school_arkas_unique');
        });
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->string('arkas_id')->nullable()->after('id');
            $t->unique(['rkas_header_id', 'arkas_id'], 'rkas_details_header_arkas_unique');
        });
        Schema::table('kas_taxes', function (Blueprint $t) {
            $t->string('arkas_id')->nullable()->after('id');
            $t->unique(['kas_transaction_id', 'arkas_id'], 'kas_taxes_trx_arkas_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kas_transactions', function (Blueprint $t) {
            $t->dropUnique('kas_transactions_school_arkas_unique');
            $t->dropColumn('arkas_id');
        });
        Schema::table('rkas_details', function (Blueprint $t) {
            $t->dropUnique('rkas_details_header_arkas_unique');
            $t->dropColumn('arkas_id');
        });
        Schema::table('kas_taxes', function (Blueprint $t) {
            $t->dropUnique('kas_taxes_trx_arkas_unique');
            $t->dropColumn('arkas_id');
        });
    }
};
