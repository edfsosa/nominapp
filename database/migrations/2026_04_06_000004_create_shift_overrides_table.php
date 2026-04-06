<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('override_date');
            // El turno que realmente debe cumplir ese día (incluye francos con is_day_off = true).
            $table->foreignId('shift_id')->constrained('shift_templates')->restrictOnDelete();
            $table->enum('reason_type', [
                'cambio_turno',  // intercambio con otro empleado
                'guardia_extra', // turno adicional fuera del ciclo
                'permiso',       // permiso autorizado
                'reposo',        // reposo médico
                'otro',
            ]);
            $table->string('notes', 150)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'override_date']); // un solo override por persona por día
            $table->index('override_date'); // para cargar overrides de un rango de fechas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_overrides');
    }
};
