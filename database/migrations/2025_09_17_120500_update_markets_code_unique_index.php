<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the plain unique constraint if it exists
        Schema::table('markets', function (Blueprint $table) {
            // Laravel's default name for unique index on `code`
            try {
                $table->dropUnique('markets_code_unique');
            } catch (\Throwable $e) {
                // Ignore if it doesn't exist
            }
        });

        // Create a partial, case-insensitive unique index (PostgreSQL)
        // Ensures unique UPPER(code) only for active rows (deleted_at IS NULL)
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS markets_code_unique_active ON markets (UPPER(code)) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        // Drop the partial unique index
        try {
            DB::statement('DROP INDEX IF EXISTS markets_code_unique_active;');
        } catch (\Throwable $e) {
            // ignore
        }

        // Restore the plain unique constraint
        Schema::table('markets', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};
