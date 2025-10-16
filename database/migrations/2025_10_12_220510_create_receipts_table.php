<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments');
            $table->foreignId('charge_id')->nullable()->constrained('charges');
            $table->foreignId('market_id')->nullable()->constrained('markets');
            $table->string('scope', 16)->default('PAYMENT');
            $table->string('concept', 32)->nullable();
            $table->string('template_version', 16)->nullable();
            $table->foreignId('parent_receipt_id')->nullable()->constrained('receipts');
            $table->string('series_code', 32);
            $table->unsignedBigInteger('number_seq');
            $table->string('receipt_number', 64)->unique();
            $table->timestamp('issued_at');
            $table->string('status', 16)->default('ACTIVE');
            $table->string('allocations_hash', 64);
            $table->string('public_token', 64)->unique();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['payment_id']);
            $table->index(['payment_id', 'charge_id']);
            $table->index(['scope', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
