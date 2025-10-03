<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condo_expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('condo_period_id')->constrained('condo_periods')->cascadeOnDelete();
            $table->foreignId('expense_type_id')->constrained('expense_types');
            $table->unsignedBigInteger('amount_usd_minor');
            $table->string('invoice_number', 60)->nullable();
            $table->date('expense_date')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['condo_period_id'], 'condo_expenses_period_index');
            $table->index(['expense_type_id'], 'condo_expenses_type_index');
            $table->index(['condo_period_id', 'expense_date'], 'condo_expenses_period_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condo_expenses');
    }
};
