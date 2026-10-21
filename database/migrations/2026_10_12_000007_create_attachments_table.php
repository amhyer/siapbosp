<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kas_transaction_id')->constrained('kas_transactions')->cascadeOnDelete();
            $t->string('path');
            $t->string('nama');
            $t->bigInteger('ukuran')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
