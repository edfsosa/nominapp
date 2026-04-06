<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra patrones de rotación, asignaciones a empleados y overrides de ejemplo.
 *
 * Escenarios cubiertos:
 *   A) Patrón "3 Turnos 21 días" (6×1): Mañana 6d + Franco / Tarde 6d + Franco / Noche 6d + Franco
 *   B) Patrón "Turnos 12h 2×2" (guardia sanitaria/industrial): 2 días día + 2 días noche + 2 francos
 *
 * Asignaciones:
 *   - 3 empleados con patrón A en distintos puntos del ciclo (start_index diferente)
 *   - 2 empleados con patrón B
 *
 * Overrides de ejemplo (última semana):
 *   - Un empleado con cambio de turno puntual (cambio_turno)
 *   - Un empleado con franco convertido en guardia extra (guardia_extra)
 */
class RotationPatternSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DB::table('companies')->value('id');
        $userId    = DB::table('users')->value('id');
        $now       = now();

        // ── Recuperar IDs de los turnos por nombre ──────────────────────────
        $shifts = DB::table('shift_templates')
            ->where('company_id', $companyId)
            ->pluck('id', 'name');

        if ($shifts->isEmpty()) {
            $this->command->warn('ShiftTemplateSeeder debe correr antes que RotationPatternSeeder.');
            return;
        }

        $idMañana    = $shifts['Turno Mañana'];
        $idTarde     = $shifts['Turno Tarde'];
        $idNoche     = $shifts['Turno Noche'];
        $idFranco    = $shifts['Franco'];
        $id12hDia    = $shifts['Turno 12h Día'];
        $id12hNoche  = $shifts['Turno 12h Noche'];

        // ── Patrón A: 3 Turnos Rotativos 21 días (6×1) ────────────────────
        // Ciclo: 6 días Mañana + Franco, 6 días Tarde + Franco, 6 días Noche + Franco
        $seqA = array_merge(
            array_fill(0, 6, $idMañana), [$idFranco],
            array_fill(0, 6, $idTarde),  [$idFranco],
            array_fill(0, 6, $idNoche),  [$idFranco]
        ); // 21 elementos

        $patternAId = DB::table('rotation_patterns')->insertGetId([
            'company_id'  => $companyId,
            'name'        => '3 Turnos Rotativos 21 días',
            'description' => 'Ciclo 6×1: Mañana → Tarde → Noche, con franco semanal',
            'sequence'    => json_encode($seqA),
            'is_active'   => true,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // ── Patrón B: Guardia 12h — 2 día + 2 noche + 2 franco ─────────────
        // Ciclo de 6 días: DD NN FF
        $seqB = [
            $id12hDia, $id12hDia,
            $id12hNoche, $id12hNoche,
            $idFranco, $idFranco,
        ]; // 6 elementos

        $patternBId = DB::table('rotation_patterns')->insertGetId([
            'company_id'  => $companyId,
            'name'        => 'Guardia 12h — 2+2+2',
            'description' => 'Ciclo de 6 días: 2 días 12h + 2 noches 12h + 2 francos. Ideal para 24/7.',
            'sequence'    => json_encode($seqB),
            'is_active'   => true,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $this->command->info('Patrones sembrados: 2 (21 días + 6 días)');

        // ── Empleados activos disponibles ───────────────────────────────────
        $employees = DB::table('employees')
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(7)
            ->pluck('id')
            ->values();

        if ($employees->count() < 3) {
            $this->command->warn('Se necesitan al menos 3 empleados activos para el seeder de rotación.');
            return;
        }

        // Fecha base: inicio de la semana en curso (lunes)
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        // ── Asignaciones patrón A ──────────────────────────────────────────
        // 3 empleados, cada uno en un punto diferente del ciclo de 21 días:
        //   Emp[0]: start_index=0  → empieza en día 1 (Turno Mañana)
        //   Emp[1]: start_index=7  → empieza en día 8 (Turno Tarde)
        //   Emp[2]: start_index=14 → empieza en día 15 (Turno Noche)
        $assignmentsA = [
            ['employee_id' => $employees[0], 'pattern_id' => $patternAId, 'start_index' => 0,  'notes' => 'Inicia ciclo en Turno Mañana'],
            ['employee_id' => $employees[1], 'pattern_id' => $patternAId, 'start_index' => 7,  'notes' => 'Inicia ciclo en Turno Tarde'],
            ['employee_id' => $employees[2], 'pattern_id' => $patternAId, 'start_index' => 14, 'notes' => 'Inicia ciclo en Turno Noche'],
        ];

        // ── Asignaciones patrón B (si hay suficientes empleados) ───────────
        $assignmentsB = [];
        if ($employees->count() >= 5) {
            $assignmentsB = [
                ['employee_id' => $employees[3], 'pattern_id' => $patternBId, 'start_index' => 0, 'notes' => 'Guardia 12h — inicio en día'],
                ['employee_id' => $employees[4], 'pattern_id' => $patternBId, 'start_index' => 2, 'notes' => 'Guardia 12h — inicio en noche'],
            ];
        }

        $allAssignments = array_merge($assignmentsA, $assignmentsB);

        foreach ($allAssignments as $a) {
            DB::table('rotation_assignments')->insert([
                'employee_id'   => $a['employee_id'],
                'pattern_id'    => $a['pattern_id'],
                'start_index'   => $a['start_index'],
                'valid_from'    => $monday->copy()->subWeeks(4)->toDateString(), // arranca 4 semanas atrás
                'valid_until'   => null,
                'notes'         => $a['notes'],
                'created_by_id' => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $this->command->info('Asignaciones de rotación sembradas: ' . count($allAssignments));

        // ── Overrides de ejemplo (esta semana) ─────────────────────────────
        // Solo si hay suficientes empleados
        if ($employees->count() < 2) {
            return;
        }

        $overrides = [
            // Empleado[0]: el miércoles de esta semana tiene Turno Tarde en lugar del que le toca
            [
                'employee_id'   => $employees[0],
                'override_date' => $monday->copy()->addDays(2)->toDateString(), // miércoles
                'shift_id'      => $idTarde,
                'reason_type'   => 'cambio_turno',
                'notes'         => 'Cubre a González que tiene cita médica',
                'created_by_id' => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            // Empleado[1]: el viernes de esta semana tiene Franco (pidió permiso)
            [
                'employee_id'   => $employees[1],
                'override_date' => $monday->copy()->addDays(4)->toDateString(), // viernes
                'shift_id'      => $idFranco,
                'reason_type'   => 'permiso',
                'notes'         => 'Permiso para trámites personales',
                'created_by_id' => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        // Evitar UNIQUE constraint si el override_date ya cayó en un franco natural del ciclo
        // (el override simplemente confirmaría el mismo estado — se inserta igual para demostrar)
        foreach ($overrides as $o) {
            $exists = DB::table('shift_overrides')
                ->where('employee_id', $o['employee_id'])
                ->where('override_date', $o['override_date'])
                ->exists();

            if (! $exists) {
                DB::table('shift_overrides')->insert($o);
            }
        }

        $this->command->info('Overrides de ejemplo sembrados: ' . count($overrides));
    }
}
