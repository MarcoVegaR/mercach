<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS charges_debt_analysis_local_due_idx ON charges USING btree (due_on, charge_status_id, debtor_id) WHERE deleted_at IS NULL AND debtor_type = 'LOCAL'");
        DB::statement("CREATE INDEX IF NOT EXISTS charges_debt_analysis_concessionaire_due_idx ON charges USING btree (due_on, charge_status_id, debtor_id) WHERE deleted_at IS NULL AND debtor_type = 'CONCESSIONAIRE'");
        DB::statement('CREATE INDEX IF NOT EXISTS payment_allocations_charge_active_idx ON payment_allocations USING btree (charge_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS credit_applications_charge_active_idx ON credit_applications USING btree (charge_id, customer_credit_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS charges_debt_analysis_local_due_idx');
        DB::statement('DROP INDEX IF EXISTS charges_debt_analysis_concessionaire_due_idx');
        DB::statement('DROP INDEX IF EXISTS payment_allocations_charge_active_idx');
        DB::statement('DROP INDEX IF EXISTS credit_applications_charge_active_idx');
    }
};
