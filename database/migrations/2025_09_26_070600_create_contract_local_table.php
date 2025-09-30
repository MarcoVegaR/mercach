<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_local', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locals')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['contract_id', 'local_id']);
            $table->index('local_id', 'contract_local_local_id_idx');
            $table->index('contract_id', 'contract_local_contract_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_local');
    }
};
