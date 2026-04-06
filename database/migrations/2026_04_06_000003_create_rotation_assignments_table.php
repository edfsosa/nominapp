<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pattern_id')->constrained('rotation_patterns')->restrictOnDelete();
            // Posición inicial en sequence[] al comienzo del assignment (0-based).
            // Ej: si el ciclo tiene 21 días y el empleado arranca en el día 8, start_index = 7.
            $table->smallInteger('start_index')->default(0);
            $table->date('valid_from');
            $table->date('valid_until')->nullable(); // null = vigente
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'valid_from']);
            $table->index(['employee_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotation_assignments');
    }
};
