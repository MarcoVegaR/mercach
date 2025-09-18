<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_locations', function (Blueprint $table) {
            $table->id();

            $table->string('code', 10);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->softDeletes();
        });

        // Ensure uniqueness of code only for non-deleted rows
        if (DB::getDriverName() === 'pgsql') {
            // Partial unique index (supports ON CONFLICT (code))
            DB::statement('CREATE UNIQUE INDEX local_locations_code_unique_active ON local_locations (code) WHERE deleted_at IS NULL');
        } else {
            // Fallback: composite unique across code+deleted_at
            Schema::table('local_locations', function (Blueprint $table) {
                $table->unique(['code', 'deleted_at'], 'local_locations_code_deleted_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS local_locations_code_unique_active');
        } else {
            Schema::table('local_locations', function (Blueprint $table) {
                $table->dropUnique('local_locations_code_deleted_unique');
            });
        }
        Schema::dropIfExists('local_locations');
    }
};
