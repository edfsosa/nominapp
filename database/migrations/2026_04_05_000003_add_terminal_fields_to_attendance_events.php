<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega terminal_id y branch_mismatch a attendance_events.
 *
 * - terminal_id: terminal desde la que se realizó la marcación (null = móvil/manual)
 * - branch_mismatch: true cuando la sucursal del empleado ≠ sucursal de la terminal
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->foreignId('terminal_id')
                ->nullable()
                ->after('branch_name')
                ->constrained('terminals')
                ->nullOnDelete();

            $table->boolean('branch_mismatch')
                ->default(false)
                ->after('terminal_id')
                ->comment('true cuando el empleado pertenece a una sucursal diferente a la de la terminal');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Terminal::class);
            $table->dropColumn(['terminal_id', 'branch_mismatch']);
        });
    }
};
