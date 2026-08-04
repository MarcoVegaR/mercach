<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->timestamp('uncollectible_at')->nullable()->after('note');
            $table->text('uncollectible_reason')->nullable()->after('uncollectible_at');
            $table->foreignId('uncollectible_by_user_id')
                ->nullable()
                ->after('uncollectible_reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['uncollectible_at', 'charge_status_id'], 'charges_uncollectible_status_idx');
            $table->index(['market_id', 'uncollectible_at'], 'charges_market_uncollectible_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropIndex('charges_market_uncollectible_idx');
            $table->dropIndex('charges_uncollectible_status_idx');
            $table->dropConstrainedForeignId('uncollectible_by_user_id');
            $table->dropColumn(['uncollectible_at', 'uncollectible_reason']);
        });
    }
};
