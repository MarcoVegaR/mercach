<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained('charges')->restrictOnDelete();

            // Denormalized for fast queries
            $table->foreignId('local_id')->constrained('locals')->cascadeOnDelete();
            $table->string('debtor_type', 20);
            $table->unsignedBigInteger('debtor_id');

            $table->unsignedBigInteger('amount_bs_minor');

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['payment_id', 'charge_id'], 'payment_allocations_unique_payment_charge');
            $table->index(['local_id'], 'payment_allocations_local_index');
            $table->index(['debtor_type', 'debtor_id'], 'payment_allocations_debtor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
