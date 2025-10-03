<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condo_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->date('period'); // first day of the month
            $table->string('status', 10)->default('DRAFT'); // DRAFT|FINAL
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['market_id', 'period'], 'condo_periods_market_period_unique');
            $table->index(['status'], 'condo_periods_status_index');
            $table->index(['market_id', 'period'], 'condo_periods_market_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condo_periods');
    }
};
