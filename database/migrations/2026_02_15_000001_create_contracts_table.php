<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Tipo de contrato según Código Laboral Paraguayo (Ley 213/93)
            $table->enum('type', ['indefinido', 'plazo_fijo', 'obra_determinada', 'aprendizaje', 'pasantia']);

            // Período
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null para contratos indefinidos
            $table->integer('trial_days')->nullable(); // Período de prueba (Art. 58 CLT - hasta 30 días)

            // Condiciones laborales
            $table->enum('salary_type', ['mensual', 'jornal'])->default('mensual'); // Art. 231 CLT: forma de remuneración
            $table->unsignedBigInteger('salary'); // Guaraníes (PYG) sin decimales
            $table->foreignId('position_id')->constrained();
            $table->foreignId('department_id')->constrained();

            // Modalidad de trabajo
            $table->enum('work_modality', ['presencial', 'remoto', 'hibrido'])->default('presencial');

            // Documento adjunto
            $table->string('document_path')->nullable();

            // Estado
            $table->enum('status', ['active', 'expired', 'terminated', 'renewed'])->default('active');

            // Metadata
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users');
            $table->timestamps();

            // Índices para rendimiento
            $table->index(['employee_id', 'status']);
            $table->index(['end_date', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
