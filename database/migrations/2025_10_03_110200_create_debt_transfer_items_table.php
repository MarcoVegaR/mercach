<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_transfer_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('debt_transfer_id')->constrained('debt_transfers')->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained('charges')->cascadeOnDelete();

            // Snapshot of charge
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->date('period');
            $table->date('issued_on');
            $table->date('due_on');
            $table->string('kind', 20);

            // Before/after
            $table->string('prev_debtor_type', 20);
            $table->unsignedBigInteger('prev_debtor_id');
            $table->string('new_debtor_type', 20);
            $table->unsignedBigInteger('new_debtor_id');

            $table->unsignedBigInteger('prev_contract_id')->nullable();
            $table->unsignedBigInteger('new_contract_id')->nullable();

            $table->timestamps();

            // Indexes for reporting
            $table->index(['debt_transfer_id'], 'debt_transfer_items_transfer_index');
            $table->index(['charge_id'], 'debt_transfer_items_charge_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_transfer_items');
    }
};
