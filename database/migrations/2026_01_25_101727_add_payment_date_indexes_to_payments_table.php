<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw statements to support PostgreSQL IF NOT EXISTS.
        DB::statement('CREATE INDEX IF NOT EXISTS payments_paid_on_index ON payments (paid_on)');
        DB::statement('CREATE INDEX IF NOT EXISTS payments_created_at_index ON payments (created_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_paid_on_index');
        DB::statement('DROP INDEX IF EXISTS payments_created_at_index');
    }
};
