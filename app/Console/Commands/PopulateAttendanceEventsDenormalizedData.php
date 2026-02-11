<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateAttendanceEventsDenormalizedData extends Command
{
    // La firma del comando y su descripción.
    protected $signature = 'attendance:populate-denormalized-data {--chunk=500 : Number of records to process per chunk}';
    protected $description = 'Populate denormalized employee and branch data in attendance_events table for better performance';

    /**
     * Ejecución del comando.
     *
     * @return void
     */
    public function handle()
    {
        // Mensaje inicial
        $this->info('Iniciando población de datos desnormalizados en attendance_events...');

        // Obtener el tamaño del chunk desde la opción
        $chunkSize = (int) $this->option('chunk');
        $totalRecords = AttendanceEvent::count();

        // Verificar si hay registros para procesar
        if ($totalRecords === 0) {
            $this->warn('No hay registros en attendance_events para procesar.');
            return 0;
        }

        // Mostrar total de registros a procesar
        $this->info("Total de registros a procesar: {$totalRecords}");

        // Inicializar barra de progreso
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();
        $processedCount = 0;
        $errorCount = 0;

        // Desactivar modos SQL estrictos para evitar problemas de inserción
        DB::statement('SET SESSION sql_mode = ""');

        // Procesar en chunks
        try {
            AttendanceEvent::query()
                ->whereNull('employee_id')
                ->with(['day.employee.branch'])
                ->chunkById($chunkSize, function ($events) use (&$processedCount, &$errorCount, $bar) {
                    // Procesar cada evento en el chunk
                    foreach ($events as $event) {
                        try {
                            $employee = $event->day?->employee;
                            $branch = $employee?->branch;

                            // Actualizar datos desnormalizados
                            if ($employee) {
                                $event->update([
                                    'employee_id' => $employee->id,
                                    'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                                    'employee_ci' => $employee->ci,
                                    'branch_id' => $branch?->id,
                                    'branch_name' => $branch?->name,
                                ]);
                                $processedCount++;
                            } else {
                                // Registrar error si no se encuentra el empleado
                                $errorCount++;
                                $this->newLine();
                                $this->warn("⚠ Evento ID {$event->id} no tiene empleado asociado");
                            }

                            // Avanzar la barra de progreso
                            $bar->advance();
                        } catch (\Exception $e) {
                            // Registrar error en la actualización
                            $errorCount++;
                            $this->newLine();
                            $this->error("Error procesando evento ID {$event->id}: " . $e->getMessage());
                            $bar->advance();
                        }
                    }
                }, 'id');

            // Finalizar barra de progreso y mostrar resumen
            $bar->finish();
            $this->newLine(2);
            $this->info("✅ Proceso completado exitosamente!");
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Total procesados', number_format($processedCount)],
                    ['Errores encontrados', number_format($errorCount)],
                    ['Tasa de éxito', round(($processedCount / $totalRecords) * 100, 2) . '%'],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            // Manejo de errores generales
            $this->newLine();
            $this->error('❌ Error durante el proceso: ' . $e->getMessage());
            return 1;
        }
    }
}
