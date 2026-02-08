<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->enum('type', ['haber', 'deduction']);
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('liquidacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_items');
    }
};
