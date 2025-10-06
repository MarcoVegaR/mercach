<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_tariffs', function (Blueprint $table) {
            $table->id();
            // Domain fields
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->date('valid_from');
            $table->unsignedBigInteger('price_per_m2_eur_minor');
            $table->boolean('is_current');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Soft delete for auditable history
            $table->softDeletes();

            // Uniqueness & query indexes
            $table->unique(['market_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_tariffs');
    }
};
