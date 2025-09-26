<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concessionaires', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('concessionaire_type_id')->constrained('concessionaire_types')->restrictOnDelete();
            $table->string('full_name', 160);
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('document_number', 30);
            $table->string('fiscal_address', 255);
            $table->string('email', 160); // NOT NULL by default
            $table->foreignId('phone_area_code_id')->nullable()->constrained('phone_area_codes')->restrictOnDelete();
            $table->string('phone_number', 7)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('id_document_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->softDeletes();

            // Indexes
            $table->index('email');
            $table->index('document_number');
            $table->index(['concessionaire_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concessionaires');
    }
};
