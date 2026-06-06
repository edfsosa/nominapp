<?php

namespace App\Observers;

use App\Models\AttendanceDay;
use App\Models\AttendanceEvent;
use App\Services\AttendanceCalculator;
use Illuminate\Support\Facades\Log;

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
     * Handle the AttendanceEvent "created" event.
     * Crear o actualizar AttendanceDay cuando se marca entrada
     */
    public function created(AttendanceEvent $attendanceEvent): void
    {
        // Solo procesar eventos de check_in (entrada)
        if ($attendanceEvent->event_type !== 'check_in') {
            return;
        }

        try {
            $day = $attendanceEvent->day;

            if (! $day) {
                Log::warning("AttendanceEvent sin AttendanceDay — evento ID {$attendanceEvent->id} ({$attendanceEvent->event_type})", [
                    'attendance_event_id' => $attendanceEvent->id,
                    'event_type' => $attendanceEvent->event_type,
                ]);

                return;
            }

            // Si el registro existe y está marcado como ausente, actualizarlo
            if ($day->status === 'absent') {
                Log::info("Entrada tardía registrada — CI {$attendanceEvent->employee_ci} {$attendanceEvent->employee_name}: de ausente a presente ({$day->date})", [
                    'attendance_day_id' => $day->id,
                    'employee_id' => $day->employee_id,
                    'date' => $day->date,
                ]);

                // Calcular y actualizar el registro
                AttendanceCalculator::apply($day);
                $day->save();
            }
        } catch (\Exception $e) {
            Log::error("Error procesando evento de asistencia ID {$attendanceEvent->id} ({$attendanceEvent->event_type}): {$e->getMessage()}", [
                'attendance_event_id' => $attendanceEvent->id,
                'employee_id' => $attendanceEvent->employee_id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle the AttendanceEvent "deleted" event.
     * Recalcular el AttendanceDay cuando se elimina un evento para evitar datos desactualizados.
     */
    public function deleted(AttendanceEvent $attendanceEvent): void
    {
        try {
            $day = $attendanceEvent->day;

            if (! $day) {
                return;
            }

            AttendanceCalculator::apply($day);
            $day->save();
        } catch (\Exception $e) {
            Log::error("Error recalculando asistencia al eliminar evento ID {$attendanceEvent->id}: {$e->getMessage()}", [
                'attendance_event_id' => $attendanceEvent->id,
                'employee_id' => $attendanceEvent->employee_id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
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
        if (! $attendanceEvent->relationLoaded('day')) {
            $attendanceEvent->load('day.employee.branch');
        }

        $employee = $attendanceEvent->day?->employee;
        $branch = $employee?->branch;

        if ($employee) {
            $attendanceEvent->employee_id = $employee->id;
            $attendanceEvent->employee_name = $employee->first_name.' '.$employee->last_name;
            $attendanceEvent->employee_ci = $employee->ci;
            $attendanceEvent->branch_id = $branch?->id;
            $attendanceEvent->branch_name = $branch?->name;
        }
    }
}
