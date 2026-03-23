<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra asignaciones de horario para empleados activos.
 *
 * Cada empleado recibe una asignación vigente (valid_until = null) que
 * refleja el schedule_id actual del empleado, con valid_from igual al
 * inicio de su contrato o al 01/01 del año anterior (el que sea mayor).
 *
 * Los primeros tres empleados con horario asignable reciben además una
 * asignación histórica cerrada (horario alternativo, vigente el año
 * anterior) para ilustrar el cambio de horario en la UI.
 *
 * La tabla se alimenta con DB::table() directamente porque:
 *   - No hay observer en EmployeeScheduleAssignment.
 *   - No hay contexto de Auth (created_by se inyecta manualmente).
 *   - Evitamos la validación de solapamiento de ScheduleAssignmentService
 *     (los datos se construyen ya sin solapamientos).
 */
class ScheduleAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')
            ->with('activeContract')
            ->whereNotNull('schedule_id')
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos con horario asignado.');
            return;
        }

        $userId      = DB::table('users')->value('id');
        $now         = now();
        $currentYear = (int) $now->year;
        $prevYear    = $currentYear - 1;

        // Horarios disponibles para asignaciones históricas (horario alternativo)
        $schedules      = DB::table('schedules')->pluck('id', 'name');
        $fallbackIds    = $schedules->values()->all(); // todos los IDs como array plano

        $rows             = [];
        $historyCount     = 0;
        $maxHistoryCount  = 3;

        foreach ($employees as $employee) {
            $contractStart  = $employee->activeContract
                ? Carbon::parse($employee->activeContract->start_date)
                : Carbon::create($prevYear, 1, 1);

            // valid_from de la asignación vigente: máx(inicio contrato, 01/01 año anterior)
            $minFrom   = Carbon::create($prevYear, 1, 1);
            $validFrom = $contractStart->gt($minFrom) ? $contractStart->copy() : $minFrom->copy();

            // ─── Historial para los primeros 3 empleados ──────────────────
            // Solo si el contrato empezó antes del año en curso y hay un
            // horario alternativo diferente al actual.
            if (
                $historyCount < $maxHistoryCount
                && $contractStart->year < $currentYear
            ) {
                // Buscar un horario diferente al actual para el historial
                $altScheduleId = collect($fallbackIds)
                    ->first(fn($id) => $id !== $employee->schedule_id);

                if ($altScheduleId) {
                    // Asignación histórica: desde inicio de contrato hasta 31/12 año anterior
                    $historyFrom  = max($contractStart->copy(), Carbon::create($prevYear, 1, 1));
                    $historyUntil = Carbon::create($prevYear, 12, 31);

                    // Solo insertamos historial si el rango es válido
                    if ($historyFrom->lte($historyUntil)) {
                        $rows[] = [
                            'employee_id' => $employee->id,
                            'schedule_id' => $altScheduleId,
                            'valid_from'  => $historyFrom->toDateString(),
                            'valid_until' => $historyUntil->toDateString(),
                            'notes'       => 'Horario anterior — cambio efectivo al inicio del año',
                            'created_by'  => $userId,
                            'created_at'  => $now,
                            'updated_at'  => $now,
                        ];

                        // La asignación vigente comienza el 01/01 del año en curso
                        $validFrom = Carbon::create($currentYear, 1, 1);
                    }

                    $historyCount++;
                }
            }

            // ─── Asignación vigente (sin fecha de cierre) ─────────────────
            $rows[] = [
                'employee_id' => $employee->id,
                'schedule_id' => $employee->schedule_id,
                'valid_from'  => $validFrom->toDateString(),
                'valid_until' => null,
                'notes'       => null,
                'created_by'  => $userId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('employee_schedule_assignments')->insert($chunk);
        }

        $total   = count($rows);
        $history = $historyCount;
        $this->command->info("Asignaciones sembradas: $total filas ($history con historial de cambio).");
    }
}
