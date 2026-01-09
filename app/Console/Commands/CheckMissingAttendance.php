<?php

namespace App\Console\Commands;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use App\Services\AttendanceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckMissingAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:check-missing
                            {--date= : Fecha específica a procesar (formato Y-m-d). Si no se especifica, usa hoy}
                            {--dry-run : Ejecutar en modo prueba sin crear registros}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica empleados sin marcación y crea registros de ausencia si pasó el tiempo límite';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $dryRun = $this->option('dry-run');

        $this->info("🔍 Verificando ausencias para: " . $date->format('d/m/Y H:i'));

        if ($dryRun) {
            $this->warn("⚠️  Modo DRY-RUN activado - No se crearán registros");
        }

        // Verificar si es domingo (día que nadie trabaja por defecto)
        if ($date->isSunday()) {
            $this->info("📅 Es domingo, no se procesan ausencias automáticas");
            return Command::SUCCESS;
        }

        $thresholdMinutes = config('attendance.absence_threshold_minutes', 30);
        $this->info("⏱️  Umbral configurado: {$thresholdMinutes} minutos después de la hora de entrada");

        // Obtener empleados activos con horario asignado
        $employees = Employee::where('status', 'active')
            ->whereNotNull('schedule_id')
            ->with(['schedule.days'])
            ->get();

        $this->info("👥 Empleados activos con horario: " . $employees->count());

        $processed = 0;
        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $result = $this->processEmployee($employee, $date, $thresholdMinutes, $dryRun);

            $processed++;

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'skipped') {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("✅ Proceso completado:");
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Empleados procesados', $processed],
                ['Ausencias creadas', $created],
                ['Omitidos (ya tienen registro o no aplica)', $skipped],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Procesa un empleado individual
     */
    protected function processEmployee(Employee $employee, Carbon $date, int $thresholdMinutes, bool $dryRun): string
    {
        // Verificar si ya tiene registro para hoy
        $existingRecord = AttendanceDay::where('employee_id', $employee->id)
            ->where('date', $date->toDateString())
            ->first();

        if ($existingRecord) {
            return 'skipped'; // Ya tiene registro
        }

        // Obtener el horario del empleado para el día de la semana actual
        $dayOfWeek = $date->dayOfWeekIso; // 1=Lunes, 7=Domingo
        $scheduleDay = $employee->schedule?->days->firstWhere('day_of_week', $dayOfWeek);

        if (!$scheduleDay) {
            // No tiene horario definido para este día (día libre)
            return 'skipped';
        }

        if ($scheduleDay->is_day_off) {
            // Es día libre del empleado
            return 'skipped';
        }

        // Obtener hora de entrada esperada
        $expectedCheckIn = $scheduleDay->start_time;

        if (!$expectedCheckIn) {
            return 'skipped';
        }

        // Calcular la hora límite (hora esperada + threshold)
        $expectedCheckInTime = Carbon::parse($date->toDateString() . ' ' . $expectedCheckIn);
        $thresholdTime = $expectedCheckInTime->copy()->addMinutes($thresholdMinutes);

        // Solo crear ausencia si ya pasó el tiempo límite
        if (now()->lessThan($thresholdTime)) {
            // Aún no ha pasado el tiempo límite
            return 'skipped';
        }

        // Crear registro de ausencia
        if (!$dryRun) {
            try {
                $attendanceDay = AttendanceDay::create([
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'status' => 'absent',
                    'is_calculated' => false,
                    'expected_check_in' => $expectedCheckIn,
                    'expected_check_out' => $scheduleDay->end_time,
                    'expected_break_minutes' => $scheduleDay->total_break_minutes,
                ]);

                // Aplicar cálculo para verificar vacaciones/permisos
                AttendanceCalculator::apply($attendanceDay);
                $attendanceDay->save();

                Log::info("Ausencia creada automáticamente", [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                    'date' => $date->toDateString(),
                    'expected_check_in' => $expectedCheckIn,
                    'threshold_time' => $thresholdTime->format('H:i'),
                ]);

                $this->line("  ✓ Ausencia creada: {$employee->first_name} {$employee->last_name} (Esperado: {$expectedCheckIn})");
            } catch (\Exception $e) {
                Log::error("Error creando ausencia automática", [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("  ✗ Error creando ausencia para: {$employee->first_name} {$employee->last_name}");
                return 'skipped';
            }
        } else {
            $this->line("  [DRY-RUN] Se crearía ausencia: {$employee->first_name} {$employee->last_name} (Esperado: {$expectedCheckIn})");
        }

        return 'created';
    }
}
