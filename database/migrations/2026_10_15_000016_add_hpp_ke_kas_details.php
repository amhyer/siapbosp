<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kas_details', function (Blueprint $t) {
            $t->string('sumber', 20)->default('langsung'); // langsung | siplah
            $t->foreignId('rkas_detail_id')->nullable()->constrained('rkas_details')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kas_details', function (Blueprint $t) {
            $t->dropConstrainedForeignId('rkas_detail_id');
            $t->dropColumn(['sumber']);
        });
    }
};
