<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateAttendanceEventsDenormalizedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:populate-denormalized-data {--chunk=500 : Number of records to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate denormalized employee and branch data in attendance_events table for better performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando población de datos desnormalizados en attendance_events...');

        $chunkSize = (int) $this->option('chunk');
        $totalRecords = AttendanceEvent::count();

        if ($totalRecords === 0) {
            $this->warn('No hay registros en attendance_events para procesar.');
            return 0;
        }

        $this->info("Total de registros a procesar: {$totalRecords}");

        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        // Usar query directa SQL para máximo rendimiento
        // Actualiza en chunks para evitar bloqueos de tabla
        DB::statement('SET SESSION sql_mode = ""'); // Permitir actualizaciones sin validación estricta temporalmente

        try {
            AttendanceEvent::query()
                ->whereNull('employee_id') // Solo procesar registros sin datos desnormalizados
                ->with(['day.employee.branch'])
                ->chunkById($chunkSize, function ($events) use (&$processedCount, &$errorCount, $bar) {
                    foreach ($events as $event) {
                        try {
                            $employee = $event->day?->employee;
                            $branch = $employee?->branch;

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
                                // Evento sin empleado asociado (dato huérfano)
                                $errorCount++;
                                $this->newLine();
                                $this->warn("⚠ Evento ID {$event->id} no tiene empleado asociado");
                            }

                            $bar->advance();
                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->newLine();
                            $this->error("Error procesando evento ID {$event->id}: " . $e->getMessage());
                            $bar->advance();
                        }
                    }
                }, 'id');

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
            $this->newLine();
            $this->error('❌ Error durante el proceso: ' . $e->getMessage());
            return 1;
        }
    }
}
