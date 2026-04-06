<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotation_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('description', 150)->nullable();
            // Array JSON ordenado de shift_template IDs. Ej: [10, 10, 10, 10, 10, 10, 12]
            // El largo del array define el cycle_length_days.
            $table->json('sequence');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotation_patterns');
    }
};
