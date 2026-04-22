<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');        // verbal, written, severe
            $table->string('reason');      // categoría del motivo
            $table->text('description');   // descripción detallada del hecho
            $table->date('issued_at');     // fecha de la amonestación
            $table->foreignId('issued_by_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable(); // PDF firmado subido
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warnings');
    }
};
