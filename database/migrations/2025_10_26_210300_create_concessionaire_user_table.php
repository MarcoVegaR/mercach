<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concessionaire_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('concessionaire_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('invited');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['concessionaire_id', 'user_id']);
            $table->index(['user_id']);
            $table->index(['concessionaire_id']);

            $table->foreign('concessionaire_id')->references('id')->on('concessionaires')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concessionaire_user');
    }
};
