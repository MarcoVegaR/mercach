<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('customer_credit_id')->constrained('customer_credits')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('charge_id')->constrained('charges')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->index(['payment_id', 'charge_id'], 'credit_applications_payment_charge_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};
