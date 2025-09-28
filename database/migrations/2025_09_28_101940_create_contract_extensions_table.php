<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->date('from_end_date');
            $table->date('to_end_date');
            $table->string('pdf_path', 255)->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'to_end_date'], 'contract_extensions_contract_to_end_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_extensions');
    }
};
