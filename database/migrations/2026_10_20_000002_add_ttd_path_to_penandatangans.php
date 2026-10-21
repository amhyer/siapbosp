<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penandatangans', function (Blueprint $t) {
            $t->string('ttd_path')->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('penandatangans', function (Blueprint $t) {
            $t->dropColumn('ttd_path');
        });
    }
};
