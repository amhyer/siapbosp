<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arkas_import_runs', function (Blueprint $table) {
            $table->string('jenis', 24)->default('impor');
            $table->string('sumber')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('arkas_import_runs', function (Blueprint $table) {
            $table->dropColumn(['jenis', 'sumber']);
        });
    }
};
