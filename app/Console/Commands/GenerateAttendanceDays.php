<?php

namespace App\Console\Commands;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateAttendanceDays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-attendance-days';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera registros AttendanceDay para todos los empleados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        // Verificar si el día es feriado
        $isHoliday = Holiday::whereDate('date', $today)->exists();
        if ($isHoliday) {
            $this->info("Hoy es feriado: {$today->format('Y-m-d')}. No se generarán registros de asistencia.");
            return;
        }

        // Obtener todos los empleados activos
        $employees = Employee::where('status', 'active')->get();

        foreach ($employees as $employee) {
            // Verificar si ya existe un AttendanceDay para el empleado en el día actual
            $attendanceExists = AttendanceDay::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->exists();

            if ($attendanceExists) {
                $this->info("El empleado {$employee->first_name} {$employee->last_name} ya tiene un registro de asistencia para hoy: {$today->format('Y-m-d')}. No se generará un nuevo registro.");
                continue;
            }

            // Validar si el empleado está de vacaciones
            $onVacation = Vacation::where('employee_id', $employee->id)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            // si esta de vacaciones, no registrar ningun attendanceday
            if ($onVacation) {
                $this->info("El empleado {$employee->first_name} {$employee->last_name} está de vacaciones hoy: {$today->format('Y-m-d')}. No se generará registro de asistencia.");
                continue;
            }

            // Validar si el empleado tiene un permiso o licencia
            $onLeave = EmployeeLeave::where('employee_id', $employee->id)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            if ($onLeave) {
                AttendanceDay::create([
                    'employee_id' => $employee->id,
                    'date' => $today,
                    'status' => 'on_leave',
                ]);
                $this->info("El empleado {$employee->first_name} {$employee->last_name} tiene un permiso hoy: {$today->format('Y-m-d')}. Registro de asistencia creado como 'on_leave'.");
                continue;
            }

            // Validar si el empleado tiene libre según su horario
            $dayOfWeek = $today->dayOfWeek; // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
            if ($employee->schedule->isDayOff($dayOfWeek)) {
                // No generar ningún registro si el empleado tiene día libre
                $this->info("El empleado {$employee->first_name} {$employee->last_name} tiene día libre hoy: {$today->format('Y-m-d')}. No se generará registro de asistencia.");
                continue;
            }

            // Generar registro predeterminado (ausente)
            AttendanceDay::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'status' => 'absent',
            ]);
        }

        $this->info('Proceso de generación de registros AttendanceDay completado.');
    }
}
