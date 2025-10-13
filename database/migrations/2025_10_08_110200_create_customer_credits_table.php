<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('debtor_type', 20); // CONCESSIONAIRE | LOCAL
            $table->unsignedBigInteger('debtor_id');

            $table->foreignId('source_payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->string('currency', 3)->default('VES');
            $table->unsignedBigInteger('balance_minor'); // remaining balance in minor units

            $table->string('status', 12)->default('OPEN'); // OPEN | APPLIED | REFUNDED | CLOSED
            $table->string('created_from', 20)->default('overpayment'); // overpayment|manual|adjustment|credit_note
            $table->string('notes', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['debtor_type', 'debtor_id'], 'customer_credits_debtor_index');
            $table->index(['status'], 'customer_credits_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
