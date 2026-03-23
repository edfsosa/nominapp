<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('notes', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'valid_from']);
            $table->index(['employee_id', 'valid_until']);
        });

        // Backfill: migrar asignaciones actuales desde employees.schedule_id
        DB::table('employees')
            ->whereNotNull('schedule_id')
            ->orderBy('id')
            ->each(function ($employee) {
                DB::table('employee_schedule_assignments')->insert([
                    'employee_id' => $employee->id,
                    'schedule_id' => $employee->schedule_id,
                    'valid_from'  => now()->startOfYear()->toDateString(),
                    'valid_until' => null,
                    'notes'       => 'Migrado desde asignación directa.',
                    'created_by'  => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedule_assignments');
    }
};
