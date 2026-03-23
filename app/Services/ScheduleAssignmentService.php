<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Gestiona la asignación de horarios a empleados con soporte de vigencia por fechas. */
class ScheduleAssignmentService
{
    /**
     * Asigna un horario a un empleado a partir de una fecha dada.
     *
     * Si existe una asignación activa sin fecha de cierre, la cierra el día anterior
     * a `valid_from` antes de crear la nueva. Lanza `ValidationException` si hay
     * superposición con una asignación ya cerrada que no puede resolverse
     * automáticamente.
     *
     * @param  Employee      $employee
     * @param  Schedule      $schedule
     * @param  Carbon        $validFrom   Fecha de inicio de vigencia.
     * @param  Carbon|null   $validUntil  Fecha de fin (null = indefinido).
     * @param  string|null   $notes
     * @return EmployeeScheduleAssignment
     *
     * @throws ValidationException
     */
    public static function assign(
        Employee $employee,
        Schedule $schedule,
        Carbon $validFrom,
        ?Carbon $validUntil = null,
        ?string $notes = null
    ): EmployeeScheduleAssignment {
        if ($validUntil && $validUntil->lt($validFrom)) {
            throw ValidationException::withMessages([
                'valid_until' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            ]);
        }

        return DB::transaction(function () use ($employee, $schedule, $validFrom, $validUntil, $notes) {
            // Cerrar la asignación abierta (sin valid_until) si su valid_from es anterior
            $employee->scheduleAssignments()
                ->whereNull('valid_until')
                ->where('valid_from', '<', $validFrom)
                ->update(['valid_until' => $validFrom->copy()->subDay()]);

            // Detectar superposición con asignaciones ya cerradas
            $overlap = $employee->scheduleAssignments()
                ->where('valid_from', '<=', $validUntil ?? '9999-12-31')
                ->where(fn($q) => $q
                    ->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $validFrom)
                )
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'valid_from' => 'El rango de fechas se superpone con una asignación existente.',
                ]);
            }

            return EmployeeScheduleAssignment::create([
                'employee_id' => $employee->id,
                'schedule_id' => $schedule->id,
                'valid_from'  => $validFrom,
                'valid_until' => $validUntil,
                'notes'       => $notes,
                'created_by'  => Auth::id(),
            ]);
        });
    }

    /**
     * Retorna el horario vigente de un empleado para una fecha dada.
     * Equivalente a `Employee::getScheduleForDate()` pero sin cargar la relación en el modelo.
     *
     * @param  Employee     $employee
     * @param  Carbon|null  $date  Fecha de consulta (por defecto hoy).
     * @return Schedule|null
     */
    public static function getForDate(Employee $employee, ?Carbon $date = null): ?Schedule
    {
        return $employee->getScheduleForDate($date);
    }

    /**
     * Cierra la asignación activa de un empleado en una fecha dada.
     * No hace nada si el empleado no tiene asignación abierta.
     *
     * @param  Employee  $employee
     * @param  Carbon    $closedOn  Último día de vigencia (inclusive).
     * @return int  Número de registros actualizados (0 o 1).
     */
    public static function closeActive(Employee $employee, Carbon $closedOn): int
    {
        return $employee->scheduleAssignments()
            ->whereNull('valid_until')
            ->where('valid_from', '<=', $closedOn)
            ->update(['valid_until' => $closedOn]);
    }
}
