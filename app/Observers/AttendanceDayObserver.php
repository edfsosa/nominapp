<?php

namespace App\Observers;

use App\Models\Absent;
use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;
use Illuminate\Support\Facades\Log;

class AttendanceDayObserver
{
    public function saved(AttendanceDay $day)
    {
        AttendanceCalculator::apply($day);
        $day->saveQuietly();
    }

    /**
     * Handle the AttendanceDay "created" event.
     */
    public function created(AttendanceDay $day): void
    {
        $this->createAbsentIfNeeded($day);
    }

    /**
     * Handle the AttendanceDay "updated" event.
     */
    public function updated(AttendanceDay $day): void
    {
        if ($day->wasChanged('status')) {
            $this->handleStatusChange($day);
        }
    }

    /**
     * Crea un registro de Absent si el día tiene status='absent' y no existe uno asociado
     */
    private function createAbsentIfNeeded(AttendanceDay $day): void
    {
        if ($day->status !== 'absent') {
            return;
        }

        // Verificar que no exista ya un Absent para este día
        $existingAbsent = Absent::where('attendance_day_id', $day->id)->first();

        if ($existingAbsent) {
            return;
        }

        $absent = Absent::create([
            'employee_id' => $day->employee_id,
            'attendance_day_id' => $day->id,
            'status' => 'pending',
            'reported_by_id' => null, // NULL indica que fue creado por el sistema
        ]);

        Log::info('Ausencia creada automáticamente por el sistema', [
            'absent_id' => $absent->id,
            'employee_id' => $day->employee_id,
            'employee_name' => $day->employee->full_name ?? 'N/A',
            'attendance_day_id' => $day->id,
            'date' => $day->date->format('Y-m-d'),
        ]);
    }

    /**
     * Maneja los cambios de status en AttendanceDay
     */
    private function handleStatusChange(AttendanceDay $day): void
    {
        $oldStatus = $day->getOriginal('status');
        $newStatus = $day->status;

        // Si cambia A 'absent' → crear Absent si no existe
        if ($newStatus === 'absent') {
            $this->createAbsentIfNeeded($day);
            return;
        }

        // Si cambia DE 'absent' a otro estado → eliminar Absent si está pendiente
        if ($oldStatus === 'absent' && $newStatus !== 'absent') {
            $absent = Absent::where('attendance_day_id', $day->id)->first();

            if ($absent && $absent->isPending()) {
                Log::info('Ausencia pendiente eliminada por cambio de estado', [
                    'absent_id' => $absent->id,
                    'employee_id' => $day->employee_id,
                    'employee_name' => $day->employee->full_name ?? 'N/A',
                    'attendance_day_id' => $day->id,
                    'date' => $day->date->format('Y-m-d'),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                $absent->delete();
            } elseif ($absent) {
                Log::info('Ausencia revisada NO eliminada por cambio de estado (ya fue procesada)', [
                    'absent_id' => $absent->id,
                    'absent_status' => $absent->status,
                    'employee_id' => $day->employee_id,
                    'attendance_day_id' => $day->id,
                    'date' => $day->date->format('Y-m-d'),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
            }
        }
    }
}
