<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->nullable()->constrained('markets');
            $table->string('series_code', 32);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['market_id', 'series_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_sequences');
    }
};
