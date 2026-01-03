<?php

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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 100)->nullable();
            $table->string('city', 60)->nullable();
            $table->json('coordinates')->nullable();  // Ej: -25.303772, -57.611112
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
