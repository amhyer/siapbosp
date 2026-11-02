<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kas_transactions', function (Blueprint $t) {
            $t->string('natural_key', 64)->nullable()->after('arkas_id')->index();
            $t->string('content_hash', 64)->nullable()->after('natural_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kas_transactions', function (Blueprint $t) {
            $t->dropIndex(['natural_key']);
            $t->dropColumn(['natural_key', 'content_hash']);
        });
    }
};
