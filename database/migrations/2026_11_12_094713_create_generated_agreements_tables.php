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
        Schema::create('agreement_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('generated_agreements', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence_number');
            $table->string('number', 30)->unique();
            $table->uuid('issuance_token')->unique();
            $table->foreignId('concessionaire_id')->constrained()->restrictOnDelete();
            $table->foreignId('local_id')->constrained()->restrictOnDelete();
            $table->foreignId('trade_category_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('signature_date');
            $table->json('snapshot');
            $table->string('template_version', 40);
            $table->longText('rendered_html');
            $table->string('pdf_path');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['year', 'sequence_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_agreements');
        Schema::dropIfExists('agreement_sequences');
    }
};
