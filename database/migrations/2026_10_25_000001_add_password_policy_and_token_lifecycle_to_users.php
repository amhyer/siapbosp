<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-01 & R-02: kebijakan sandi + siklus hidup token API.
 *
 * - `must_change_password` : akun hasil impor / reset Dinas wajib mengganti
 *   sandi pada login pertama (dipaksa oleh middleware `PastikanAkunAktif`).
 * - `api_token_expires_at` : token API berbatas waktu (tidak berlaku selamanya
 *   bila perangkat hilang).
 * - `api_token_used_at`    : jejak pemakaian terakhir token untuk audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('must_change_password')->default(false)->after('is_active');
            $t->timestamp('api_token_expires_at')->nullable()->after('api_token');
            $t->timestamp('api_token_used_at')->nullable()->after('api_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['must_change_password', 'api_token_expires_at', 'api_token_used_at']);
        });
    }
};
