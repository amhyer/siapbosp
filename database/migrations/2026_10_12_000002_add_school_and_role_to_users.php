<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            $t->string('role')->default('operator')->after('email'); // superadmin, dinas, operator, kepsek
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('school_id');
            $t->dropColumn('role');
        });
    }
};
