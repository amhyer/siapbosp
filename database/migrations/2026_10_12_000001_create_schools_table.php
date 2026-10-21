<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $t) {
            $t->id();
            $t->string('npsn')->unique();
            $t->string('nama');
            $t->string('jenjang')->default('SD');
            $t->string('desa')->nullable();
            $t->string('kecamatan')->nullable();
            $t->string('kab_kota')->default('Kota Makassar');
            $t->string('provinsi')->default('Sulawesi Selatan');
            $t->string('alamat')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
