<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamp('executed_at');
            $table->foreignId('performed_by_user_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locals')->cascadeOnDelete();

            // From -> To
            $table->string('from_debtor_type', 20);
            $table->unsignedBigInteger('from_debtor_id');
            $table->string('to_debtor_type', 20);
            $table->unsignedBigInteger('to_debtor_id');
            $table->foreignId('new_contract_id')->nullable()->constrained('contracts')->nullOnDelete();

            $table->foreignId('reason_id')->nullable()->constrained('debt_transfer_reasons')->nullOnDelete();
            $table->string('note', 255)->nullable();

            $table->unsignedBigInteger('total_amount_minor');
            $table->string('currency', 3);

            $table->timestamps();

            // Indexes for reporting
            $table->index(['market_id', 'executed_at'], 'debt_transfers_market_executed_at_index');
            $table->index(['local_id', 'executed_at'], 'debt_transfers_local_executed_at_index');
            $table->index(['from_debtor_type', 'from_debtor_id'], 'debt_transfers_from_debtor_index');
            $table->index(['to_debtor_type', 'to_debtor_id'], 'debt_transfers_to_debtor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_transfers');
    }
};
