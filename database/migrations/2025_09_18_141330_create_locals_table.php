<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locals', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('code', 30); // Unique handled by partial index below
            $table->string('name', 160);

            // Foreign keys
            $table->foreignId('market_id')->constrained('markets')->restrictOnDelete();
            $table->foreignId('local_type_id')->constrained('local_types')->restrictOnDelete();
            $table->foreignId('local_status_id')->constrained('local_statuses')->restrictOnDelete();
            $table->foreignId('local_location_id')->constrained('local_locations')->restrictOnDelete();

            // Other attributes
            $table->decimal('area_m2', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Create a partial, case-insensitive unique index on UPPER(code) for active rows
        // This enforces global uniqueness of code ignoring soft deletes
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS locals_code_unique_active ON locals (UPPER(code)) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        // Drop the partial unique index if present
        try {
            DB::statement('DROP INDEX IF EXISTS locals_code_unique_active;');
        } catch (\Throwable $e) {
            // ignore
        }
        Schema::dropIfExists('locals');
    }
};
