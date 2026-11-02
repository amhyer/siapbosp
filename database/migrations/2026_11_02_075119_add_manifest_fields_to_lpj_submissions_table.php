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
        Schema::table('lpj_submissions', function (Blueprint $table) {
            $table->string('manifest_hash', 64)->nullable()->after('status');
            $table->string('previous_manifest_hash', 64)->nullable()->after('manifest_hash');
            $table->unsignedBigInteger('total_pendapatan')->default(0)->after('previous_manifest_hash');
            $table->unsignedBigInteger('total_belanja')->default(0)->after('total_pendapatan');
            $table->bigInteger('sisa_dana')->default(0)->after('total_belanja');
            $table->unsignedInteger('total_transaksi')->default(0)->after('sisa_dana');
            $table->text('school_signature')->nullable()->after('total_transaksi');
            $table->text('dinas_signature')->nullable()->after('school_signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lpj_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'manifest_hash',
                'previous_manifest_hash',
                'total_pendapatan',
                'total_belanja',
                'sisa_dana',
                'total_transaksi',
                'school_signature',
                'dinas_signature',
            ]);
        });
    }
};
