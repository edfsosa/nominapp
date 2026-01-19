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
        Schema::create('absents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_day_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'justified', 'unjustified'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->foreignId('reported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->json('documents')->nullable(); // Array de rutas de documentos justificativos
            $table->foreignId('employee_deduction_id')->nullable()->constrained('employee_deductions')->nullOnDelete();
            $table->timestamps();

            // Índices para mejorar rendimiento
            $table->index(['employee_id', 'status']);
            $table->index('attendance_day_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absents');
    }
};
