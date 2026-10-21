<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rkas_headers', function (Blueprint $t) {
            $t->timestamp('dikirim_dinas_at')->nullable();
            $t->text('catatan_dinas')->nullable();
            $t->string('ditinjau_oleh')->nullable();
            $t->timestamp('ditinjau_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rkas_headers', function (Blueprint $t) {
            $t->dropColumn(['dikirim_dinas_at', 'catatan_dinas', 'ditinjau_oleh', 'ditinjau_at']);
        });
    }
};
