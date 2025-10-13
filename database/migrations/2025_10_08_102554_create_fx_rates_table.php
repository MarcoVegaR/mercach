<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();

            $table->char('currency_code', 3);
            $table->date('rate_date');
            $table->date('value_date');
            $table->timestampTz('published_at')->nullable();
            $table->decimal('rate_to_ves', 18, 6);
            $table->timestampTz('operational_from');
            $table->timestampTz('operational_to')->nullable();
            $table->string('source', 80)->nullable();
            $table->boolean('is_official')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Uniques for queries and idempotency
            $table->unique(['currency_code', 'value_date'], 'fx_rates_currency_value_date_unique');
            $table->unique(['currency_code', 'rate_date'], 'fx_rates_currency_rate_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
