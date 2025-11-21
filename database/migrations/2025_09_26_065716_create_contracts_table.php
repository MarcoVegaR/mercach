<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('number', 40); // Unique enforced below via partial index (ignoring soft-deletes)

            // Foreign keys
            $table->foreignId('contract_type_id')->constrained('contract_types')->restrictOnDelete();
            $table->foreignId('contract_status_id')->constrained('contract_statuses')->restrictOnDelete();
            $table->foreignId('contract_modality_id')->constrained('contract_modalities')->restrictOnDelete();
            $table->foreignId('trade_category_id')->constrained('trade_categories')->restrictOnDelete();

            // Dates and billing
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('billing_day')->nullable(); // only for FIXED modality
            $table->decimal('monthly_price_eur', 12, 2)->nullable(); // only for FIXED modality

            // File path (relative to public/ or storage/public symlink)
            $table->string('pdf_path', 255)->nullable();

            // State
            $table->timestamp('signed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('has_active_procedure')->default(false);
            $table->timestamps();

            // Performance index for status/date queries (overlap checks, expiration)
            $table->index(['contract_status_id', 'start_date', 'end_date'], 'contracts_status_dates_index');

            $table->softDeletes();
        });

        // Create a partial, case-insensitive unique index on UPPER(number) for active rows (deleted_at IS NULL)
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS contracts_number_unique_active ON contracts (UPPER(number)) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        // Drop the partial unique index if present
        try {
            DB::statement('DROP INDEX IF EXISTS contracts_number_unique_active;');
        } catch (\Throwable $e) {
            // ignore
        }
        // Drop composite index if present
        try {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropIndex('contracts_status_dates_index');
            });
        } catch (\Throwable $e) {
            // ignore
        }
        Schema::dropIfExists('contracts');
    }
};
