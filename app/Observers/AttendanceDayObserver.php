<?php

namespace App\Observers;

use App\Models\Absence;
use App\Models\AttendanceDay;
use Illuminate\Support\Facades\Log;

class AttendanceDayObserver
{
    /**
     * Handle the AttendanceDay "created" event.
     */
    public function created(AttendanceDay $day): void
    {
        $this->createAbsenceIfNeeded($day);
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
    private function createAbsenceIfNeeded(AttendanceDay $day): void
    {
        if ($day->status !== 'absent') {
            return;
        }

        // Verificar que no exista ya un Absent para este día
        $existingAbsence = Absence::where('attendance_day_id', $day->id)->first();

        if ($existingAbsence) {
            return;
        }

        $absent = Absence::create([
            'employee_id' => $day->employee_id,
            'attendance_day_id' => $day->id,
            'status' => 'pending',
            'reported_by_id' => null, // NULL indica que fue creado por el sistema
        ]);

        $empName = $day->employee->full_name ?? 'N/A';
        Log::info("Ausencia creada — {$empName} ({$day->date->format('d/m/Y')})", [
            'absent_id' => $absent->id,
            'employee_id' => $day->employee_id,
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
            $this->createAbsenceIfNeeded($day);

            return;
        }

        // Si cambia DE 'absent' a otro estado → eliminar Absent si está pendiente
        if ($oldStatus === 'absent' && $newStatus !== 'absent') {
            $absent = Absence::where('attendance_day_id', $day->id)->first();

            $empName = $day->employee->full_name ?? 'N/A';

            if ($absent && $absent->isPending()) {
                Log::info("Ausencia eliminada — {$empName} ({$day->date->format('d/m/Y')}): {$oldStatus} → {$newStatus}", [
                    'absent_id' => $absent->id,
                    'employee_id' => $day->employee_id,
                    'attendance_day_id' => $day->id,
                    'date' => $day->date->format('Y-m-d'),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                $absent->delete();
            } elseif ($absent) {
                Log::info("Ausencia procesada no eliminada — {$empName} ({$day->date->format('d/m/Y')}): ya tiene estado '{$absent->status}'", [
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
