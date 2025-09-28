<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('from_code', 10)->nullable();
            $table->string('to_code', 10);
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['contract_id', 'occurred_at'], 'contract_status_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_status_history');
    }
};
