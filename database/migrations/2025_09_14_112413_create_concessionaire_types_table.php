<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concessionaire_types', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concessionaire_types');
    }
};
