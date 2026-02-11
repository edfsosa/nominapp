<?php

namespace App\Console\Commands;

use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CalculateAttendance extends Command
{
    // La firma del comando y su descripción.
    protected $signature = 'app:calculate-attendance {date? : La fecha para calcular la asistencia (formato: YYYY-MM-DD)}';
    protected $description = 'Calcular horas trabajadas, descansos y registros de asistencia del día';

    /**
     * Ejecución del comando.
     *
     * @return void
     */
    public function handle()
    {
        // Obtener la fecha del argumento o usar la fecha actual
        $date = $this->argument('date') ?? Carbon::now()->toDateString();
        $dateFormatted = Carbon::parse($date)->format('d/m/Y');

        $this->info("🕒 Iniciando cálculo de asistencia para: {$dateFormatted}");

        // Contadores para estadísticas
        $stats = [
            'total' => 0,
            'calculated' => 0,
            'recalculated' => 0,
            'failed' => 0,
            'present' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'holiday' => 0,
            'weekend' => 0,
        ];

        // Procesar los registros de asistencia
        AttendanceDay::with('employee', 'events')
            ->where('date', $date)
            ->chunk(100, function ($days) use (&$stats) {
                foreach ($days as $day) {
                    $stats['total']++;
                    $wasCalculated = $day->is_calculated;

                    try {
                        AttendanceCalculator::apply($day);
                        $day->save();

                        // Actualizar estadísticas
                        $wasCalculated ? $stats['recalculated']++ : $stats['calculated']++;
                        $stats[$day->status] = ($stats[$day->status] ?? 0) + 1;
                    } catch (\Exception $e) {
                        $stats['failed']++;
                        Log::error("Error calculando AttendanceDay {$day->id}: {$e->getMessage()}");
                        $this->error("  ✗ Error procesando ID: {$day->id}");
                    }
                }
            });

        // Mostrar resultados
        $this->newLine();

        if ($stats['total'] === 0) {
            $this->warn("⚠️  No se encontraron registros de asistencia para la fecha: {$dateFormatted}");
            return Command::SUCCESS;
        }

        // Log para auditoría
        Log::info("Cálculo de asistencia completado para {$date}", $stats);

        // Mostrar tabla de estadísticas
        $this->info("📊 Resumen del cálculo - {$dateFormatted}:");
        $this->newLine();

        // Tabla de estadísticas
        $this->table(
            ['Estado', 'Cantidad'],
            [
                ['✅ Presentes', $stats['present']],
                ['❌ Ausentes', $stats['absent']],
                ['📋 De permiso', $stats['on_leave']],
                ['🎉 Feriado', $stats['holiday']],
                ['📅 Fin de semana', $stats['weekend']],
                ['---', '---'],
                ['🆕 Calculados (nuevos)', $stats['calculated']],
                ['🔄 Recalculados', $stats['recalculated']],
                ['✗ Errores', $stats['failed']],
                ['---', '---'],
                ['📊 TOTAL PROCESADOS', $stats['total']],
            ]
        );

        // Mensaje final
        $this->newLine();

        // Manejo de errores
        if ($stats['failed'] > 0) {
            $this->warn("⚠️  Completado con {$stats['failed']} error(es). Revisa los logs para más detalles.");
            return Command::FAILURE;
        }

        // Mensaje de éxito
        $this->info("✅ Cálculo de asistencia finalizado exitosamente.");
        return Command::SUCCESS;
    }
}
