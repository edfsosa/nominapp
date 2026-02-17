<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleEmployeeController extends Controller
{
    /**
     * Remover un empleado de un horario
     */
    public function removeEmployee(Schedule $schedule, Employee $employee)
    {
        try {
            // Verificar que el empleado tiene este horario asignado
            if ($employee->schedule_id !== $schedule->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El empleado no tiene este horario asignado.'
                ], 400);
            }

            // Remover el horario del empleado
            $employee->schedule_id = null;
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => "El horario fue removido de {$employee->full_name} exitosamente."
            ]);
        } catch (\Exception $e) {
            Log::error('Error al remover horario de empleado', [
                'schedule_id' => $schedule->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al remover el horario. Intente nuevamente.',
            ], 500);
        }
    }
}
