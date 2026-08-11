<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('concessionaires', function (Blueprint $table) {
            $table->date('last_life_proof_at')->nullable()->index();
        });

        Schema::create('life_proof_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->unsignedBigInteger('next_number')->default(100);
            $table->timestamps();
        });

        DB::table('life_proof_sequences')->insert([
            'key' => 'concessionaire-form',
            'next_number' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('life_proof_sequences');

        Schema::table('concessionaires', function (Blueprint $table) {
            $table->dropIndex(['last_life_proof_at']);
            $table->dropColumn('last_life_proof_at');
        });
    }
};
