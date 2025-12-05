<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->char('account_number', 20);
            $table->char('phone_number', 12)->nullable();
            $table->string('account_holder_name', 160);
            $table->char('document_type', 1);
            $table->string('document_number', 12);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_transfer')->default(true);
            $table->boolean('allow_pmov')->default(true);
            $table->boolean('allow_debit')->default(false);
            $table->timestamps();

            $table->softDeletes();

            $table->index(['bank_id'], 'company_bank_accounts_bank_index');
            $table->index(['account_number'], 'company_bank_accounts_account_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};
