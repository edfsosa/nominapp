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
        Schema::table('attendance_events', function (Blueprint $table) {
            // Columnas desnormalizadas para evitar JOINs costosos
            // Estos datos se copian desde las relaciones para mejorar performance

            $table->unsignedBigInteger('employee_id')->nullable()->after('attendance_day_id');
            $table->string('employee_name')->nullable()->after('employee_id');
            $table->string('employee_ci', 20)->nullable()->after('employee_name');
            $table->unsignedBigInteger('branch_id')->nullable()->after('employee_ci');
            $table->string('branch_name')->nullable()->after('branch_id');

            // Índices para mejorar filtros directos
            $table->index('employee_id', 'idx_employee_id');
            $table->index('branch_id', 'idx_branch_id');
            $table->index(['employee_id', 'recorded_at'], 'idx_employee_recorded_at');

            // FKs opcionales (no estrictas para evitar problemas si se eliminan empleados)
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            // Eliminar FKs primero
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['branch_id']);

            // Eliminar índices
            $table->dropIndex('idx_employee_id');
            $table->dropIndex('idx_branch_id');
            $table->dropIndex('idx_employee_recorded_at');

            // Eliminar columnas
            $table->dropColumn([
                'employee_id',
                'employee_name',
                'employee_ci',
                'branch_id',
                'branch_name',
            ]);
        });
    }
};
