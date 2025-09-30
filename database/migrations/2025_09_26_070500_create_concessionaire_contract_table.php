<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concessionaire_contract', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('concessionaire_id')->constrained('concessionaires')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['contract_id', 'concessionaire_id']);
            $table->index('concessionaire_id', 'concessionaire_contract_concessionaire_id_idx');
            $table->index('contract_id', 'concessionaire_contract_contract_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concessionaire_contract');
    }
};
