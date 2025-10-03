<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condo_participants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('condo_period_id')->constrained('condo_periods')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locals');
            $table->decimal('area_m2_snapshot', 8, 2);
            $table->boolean('included')->default(true);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['condo_period_id'], 'condo_participants_period_index');
            $table->index(['local_id'], 'condo_participants_local_index');
            $table->index(['condo_period_id', 'included'], 'condo_participants_period_included_index');
        });

        // PostgreSQL partial unique index to ignore soft-deleted rows
        DB::statement('CREATE UNIQUE INDEX condo_participants_unique_active ON condo_participants (condo_period_id, local_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        // Drop partial index explicitly before dropping table
        try {
            DB::statement('DROP INDEX IF EXISTS condo_participants_unique_active');
        } catch (\Throwable $e) {
            // ignore
        }
        Schema::dropIfExists('condo_participants');
    }
};
