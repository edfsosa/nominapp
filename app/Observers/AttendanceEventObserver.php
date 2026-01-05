<?php

namespace App\Observers;

use App\Models\AttendanceEvent;

class AttendanceEventObserver
{
    /**
     * Handle the AttendanceEvent "creating" event.
     * Poblar datos desnormalizados antes de crear el registro
     */
    public function creating(AttendanceEvent $attendanceEvent): void
    {
        $this->populateDenormalizedData($attendanceEvent);
    }

    /**
     * Handle the AttendanceEvent "updating" event.
     * Actualizar datos desnormalizados si cambia el attendance_day_id
     */
    public function updating(AttendanceEvent $attendanceEvent): void
    {
        // Solo repoblar si cambió la relación con el día de asistencia
        if ($attendanceEvent->isDirty('attendance_day_id')) {
            $this->populateDenormalizedData($attendanceEvent);
        }
    }

    /**
     * Poblar campos desnormalizados desde las relaciones
     */
    protected function populateDenormalizedData(AttendanceEvent $attendanceEvent): void
    {
        // Cargar las relaciones si no están cargadas
        if (!$attendanceEvent->relationLoaded('day')) {
            $attendanceEvent->load('day.employee.branch');
        }

        $employee = $attendanceEvent->day?->employee;
        $branch = $employee?->branch;

        if ($employee) {
            $attendanceEvent->employee_id = $employee->id;
            $attendanceEvent->employee_name = $employee->first_name . ' ' . $employee->last_name;
            $attendanceEvent->employee_ci = $employee->ci;
            $attendanceEvent->branch_id = $branch?->id;
            $attendanceEvent->branch_name = $branch?->name;
        }
    }
}
