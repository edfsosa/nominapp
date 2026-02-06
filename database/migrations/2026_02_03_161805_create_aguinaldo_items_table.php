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
        Schema::create('aguinaldo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aguinaldo_id')->constrained()->cascadeOnDelete();
            $table->string('month', 20);
            $table->decimal('base_salary', 12, 2);
            $table->decimal('perceptions', 12, 2);
            $table->decimal('extra_hours', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aguinaldo_items');
    }
};
