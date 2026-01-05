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
            // Índice en recorded_at para ordenamiento y filtro "hoy"
            // Mejora queries con ORDER BY recorded_at y WHERE DATE(recorded_at)
            $table->index('recorded_at', 'idx_recorded_at');

            // Índice compuesto para filtros combinados fecha + tipo
            // Optimiza queries que filtran por fecha Y tipo de evento simultáneamente
            $table->index(['recorded_at', 'event_type'], 'idx_recorded_at_event_type');

            // Nota: attendance_day_id ya tiene índice por la FK (línea 21 de la migración original)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex('idx_recorded_at');
            $table->dropIndex('idx_recorded_at_event_type');
        });
    }
};
