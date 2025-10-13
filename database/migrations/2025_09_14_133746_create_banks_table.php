<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();

            // Legacy code kept for compatibility; new 4-digit bank_code is the canonical identifier
            $table->string('code', 20)->unique();
            $table->char('bank_code', 4)->nullable()->unique();
            $table->string('name', 160);
            $table->string('swift_bic', 11)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
