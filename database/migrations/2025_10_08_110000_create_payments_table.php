<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Context
            $table->foreignId('local_id')->nullable()->constrained('locals')->nullOnDelete();
            $table->string('debtor_type', 20); // CONCESSIONAIRE | LOCAL
            $table->unsignedBigInteger('debtor_id');

            // Receiving company account
            $table->foreignId('company_bank_account_id')->constrained('company_bank_accounts')->restrictOnDelete();

            // Method & origin info
            $table->string('method', 20)->nullable(); // legacy code for compatibility (e.g., PMOV, TRANSFER, DEB)
            $table->foreignId('payment_type_id')->nullable()->constrained('payment_types')->nullOnDelete();
            $table->foreignId('origin_bank_id')->constrained('banks')->restrictOnDelete();
            $table->foreignId('payer_document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->string('payer_document_number', 12);
            $table->char('payer_account_number', 20)->nullable(); // required if TRANSFER
            $table->char('payer_phone_e164', 12)->nullable();     // required if PAGOMOVIL (e.g., 58XXXXXXXXXXX)
            $table->string('reference', 40);

            // Amount & FX
            $table->unsignedBigInteger('amount_bs_minor');
            $table->date('paid_on');
            $table->foreignId('fx_rate_id')->nullable()->constrained('fx_rates')->nullOnDelete();

            // Status lifecycle (FK to payment_statuses)
            $table->foreignId('payment_status_id')->nullable()->constrained('payment_statuses')->nullOnDelete();

            // Gateway auditing
            $table->json('gateway_request')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('gateway_resp_code', 8)->nullable(); // e.g., "00"
            $table->string('gateway_message', 255)->nullable();

            // Payer details passthrough for API
            $table->json('payer_details')->nullable();

            // Idempotency
            $table->string('idempotency_key', 64)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['debtor_type', 'debtor_id'], 'payments_debtor_index');
            $table->index(['local_id'], 'payments_local_index');
            $table->index(['payment_status_id'], 'payments_payment_status_index');
            $table->unique(['idempotency_key'], 'payments_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
