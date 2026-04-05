<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla de terminales de marcación de asistencia.
 * Cada terminal está asociada a una sucursal y tiene un código único
 * que se usa en la URL /terminal/{code}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 12)->unique()->comment('Código aleatorio inmutable usado en la URL /terminal/{code}');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Datos del dispositivo físico (todos opcionales)
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('device_serial')->nullable();
            $table->string('device_mac', 17)->nullable()->comment('Formato AA:BB:CC:DD:EE:FF');
            $table->text('device_notes')->nullable();

            // Instalación
            $table->date('installed_at')->nullable();
            $table->foreignId('installed_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Actividad
            $table->timestamp('last_seen_at')->nullable()->comment('Se actualiza en cada page load de la terminal');

            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
