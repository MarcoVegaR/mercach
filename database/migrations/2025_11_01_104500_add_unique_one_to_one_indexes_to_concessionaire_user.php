<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concessionaire_user', function (Blueprint $table) {
            // Enforce 1:1 relationship at DB level
            // Note: This assumes there are no duplicates. If there are, this migration will fail and data must be cleaned first.
            $table->unique('concessionaire_id', 'concessionaire_user_concessionaire_id_unique');
            $table->unique('user_id', 'concessionaire_user_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('concessionaire_user', function (Blueprint $table) {
            $table->dropUnique('concessionaire_user_concessionaire_id_unique');
            $table->dropUnique('concessionaire_user_user_id_unique');
        });
    }
};
