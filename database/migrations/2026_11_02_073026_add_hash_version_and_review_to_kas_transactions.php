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
        Schema::table('kas_transactions', function (Blueprint $table) {
            $table->unsignedTinyInteger('hash_version')->default(1)->after('content_hash');
            $table->boolean('needs_review')->default(false)->after('hash_version');
            $table->text('review_note')->nullable()->after('needs_review');
            $table->unsignedBigInteger('legacy_duplicate_of')->nullable()->after('review_note')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kas_transactions', function (Blueprint $table) {
            $table->dropIndex(['legacy_duplicate_of']);
            $table->dropColumn(['hash_version', 'needs_review', 'review_note', 'legacy_duplicate_of']);
        });
    }
};
