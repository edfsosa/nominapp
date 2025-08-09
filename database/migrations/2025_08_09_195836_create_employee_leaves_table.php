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
        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'medical_leave',        // reposo médico
                'vacation',             // vacaciones
                'day_off',              // día libre
                'maternity_leave',      // permiso maternidad
                'paternity_leave',      // permiso paternidad
                'unpaid_leave',         // permiso sin goce de sueldo
                'other'                 // otro
            ]);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable(); // descripción/motivo
            $table->string('document_path')->nullable(); // comprobante médico u otro
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->index(['employee_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
    }
};
