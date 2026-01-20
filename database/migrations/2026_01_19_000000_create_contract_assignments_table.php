<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('old_contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('new_contract_id')->constrained('contracts')->cascadeOnDelete();

            $table->foreignId('from_concessionaire_id')->constrained('concessionaires')->restrictOnDelete();
            $table->foreignId('to_concessionaire_id')->constrained('concessionaires')->restrictOnDelete();

            $table->date('effective_date');
            $table->string('reason', 255)->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['old_contract_id', 'effective_date'], 'contract_assignments_old_idx');
            $table->index(['new_contract_id', 'effective_date'], 'contract_assignments_new_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_assignments');
    }
};
