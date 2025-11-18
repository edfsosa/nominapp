<?php

namespace App\Console\Commands;

use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateAttendance extends Command
{
    protected $signature = 'app:calculate-attendance {date? : La fecha para calcular la asistencia (formato: YYYY-MM-DD)}';

    protected $description = 'Calcular horas trabajadas, descansos y registros de asistencia del día';

    // Constantes para mensajes
    private const START_MESSAGE = "🕒 Iniciando cálculo de asistencia para: ";
    private const NO_RECORDS_MESSAGE = "⚠️ No se encontraron registros de asistencia para la fecha: ";
    private const SUCCESS_MESSAGE = "✅ Cálculo de asistencia finalizado correctamente.";
    private const PROCESSED_MESSAGE = "✅ Procesado ID: ";

    public function handle()
    {
        // Obtener la fecha del argumento o usar la fecha actual
        $date = $this->argument('date') ?? Carbon::now()->toDateString();

        // Mostrar mensaje de inicio
        $this->info(self::START_MESSAGE . $date);

        // Procesar los registros de asistencia
        $recordsProcessed = $this->processAttendanceForDate($date);

        // Mostrar mensaje según el resultado
        if (! $recordsProcessed) {
            $this->warn(self::NO_RECORDS_MESSAGE . $date);
        } else {
            $this->info(self::SUCCESS_MESSAGE);
        }

        return Command::SUCCESS;
    }

    /**
     * Procesa los registros de asistencia para una fecha específica.
     *
     * @param string $date
     * @return bool
     */
    private function processAttendanceForDate(string $date): bool
    {
        $recordsFound = false;

        AttendanceDay::with('employee', 'events')
            ->where('date', $date)
            ->chunk(100, function ($days) use (&$recordsFound) {
                $recordsFound = true;

                foreach ($days as $day) {
                    AttendanceCalculator::apply($day);
                    $day->saveQuietly();
                    $this->logProcessedRecord($day->id);
                }
            });

        return $recordsFound;
    }

    /**
     * Muestra un mensaje en la consola para un registro procesado.
     *
     * @param int $id
     * @return void
     */
    private function logProcessedRecord(int $id): void
    {
        $this->line(self::PROCESSED_MESSAGE . $id);
    }
}
