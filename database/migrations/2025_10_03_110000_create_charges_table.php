<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Context
            $table->foreignId('market_id')->constrained('markets')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locals')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('condo_period_id')->nullable()->constrained('condo_periods')->cascadeOnDelete();

            // Debtor (current)
            $table->string('debtor_type', 20); // CONCESSIONAIRE | LOCAL
            $table->unsignedBigInteger('debtor_id');

            // Origin debtor (immutable)
            $table->string('origin_debtor_type', 20);
            $table->unsignedBigInteger('origin_debtor_id');

            // Classification
            $table->string('kind', 20); // RENT_EUR_M2 | RENT_EUR_FIXED | CONDO_USD
            $table->string('currency', 3); // EUR | USD

            // Amount
            $table->unsignedBigInteger('amount_minor'); // minor units

            // Dates
            $table->date('period'); // first day of month
            $table->date('issued_on');
            $table->date('due_on');

            // Status and source
            $table->foreignId('charge_status_id')->constrained('charge_statuses')->restrictOnDelete();
            $table->string('source', 20); // RENT_RUN | FIXED_RUN | CONDO_RUN
            $table->string('idempotency_key', 64)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Query indexes
            $table->index(['debtor_type', 'debtor_id'], 'charges_debtor_index');
            $table->index(['market_id', 'period', 'kind'], 'charges_market_period_kind_index');
            $table->index(['contract_id'], 'charges_contract_index');
            $table->index(['idempotency_key'], 'charges_idempotency_key_index');
        });

        // Partial unique indexes per type (PostgreSQL)
        DB::statement("CREATE UNIQUE INDEX charges_unique_rent_m2_by_debtor ON charges (debtor_type, debtor_id, kind, period) WHERE kind = 'RENT_EUR_M2' AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX charges_unique_rent_m2_avail_by_debtor ON charges (debtor_type, debtor_id, kind, period) WHERE kind = 'RENT_EUR_M2_AVAIL' AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX charges_unique_rent_fixed ON charges (contract_id, local_id, kind, issued_on) WHERE kind = 'RENT_EUR_FIXED' AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX charges_unique_condo ON charges (condo_period_id, local_id, kind) WHERE kind = 'CONDO_USD' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        // Drop partial unique indexes if exist
        try {
            DB::statement('DROP INDEX IF EXISTS charges_unique_rent_m2_by_debtor');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('DROP INDEX IF EXISTS charges_unique_rent_m2_avail_by_debtor');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('DROP INDEX IF EXISTS charges_unique_rent_fixed');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('DROP INDEX IF EXISTS charges_unique_condo');
        } catch (\Throwable $e) {
        }
        Schema::dropIfExists('charges');
    }
};
