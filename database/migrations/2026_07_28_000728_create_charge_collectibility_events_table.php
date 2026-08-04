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
        Schema::create('charge_collectibility_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained('charges')->cascadeOnDelete();
            $table->string('action', 40);
            $table->text('reason');
            $table->bigInteger('outstanding_amount_minor')->default(0);
            $table->bigInteger('outstanding_bs_minor')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['charge_id', 'occurred_at'], 'charge_collectibility_events_charge_date_idx');
            $table->index(['action', 'occurred_at'], 'charge_collectibility_events_action_date_idx');
            $table->index('user_id', 'charge_collectibility_events_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_collectibility_events');
    }
};
