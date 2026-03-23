<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra solicitudes de vacaciones y balances de empleados activos.
 *
 * El flujo correcto es:
 *   1. Definir las solicitudes de vacaciones con fechas fijas.
 *   2. Calcular used_days y pending_days del balance a partir de ellas.
 *   3. Insertar balances y luego vacaciones con la FK al balance.
 *
 * Días según Ley 213/93 (Código Laboral Paraguayo):
 *   - 0–5 años de servicio  →  12 días hábiles
 *   - 5–10 años             →  18 días hábiles
 *   - 10+ años              →  30 días hábiles
 */
class VacationSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')
            ->with('activeContract')
            ->get()
            ->values();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar vacaciones.');
            return;
        }

        DB::transaction(function () use ($employees) {
            $now  = now();
            $year = (int) date('Y');

            // ─── Definición de vacaciones (fechas fijas en el año en curso) ─────────
            // Formato: [índice empleado, start, end, status, reason]
            $vacationDefs = [
                [0, "$year-01-13", "$year-01-22", 'approved', 'Descanso anual programado'],
                [1, "$year-02-03", "$year-02-07", 'approved', 'Descanso anual programado'],
                [2, "$year-02-17", "$year-02-21", 'pending',  'Solicitud de descanso anual'],
                [3, "$year-01-06", "$year-01-15", 'approved', 'Descanso anual programado'],
                [4, "$year-03-03", "$year-03-07", 'approved', 'Descanso anual programado'],
                [5, "$year-02-24", "$year-02-28", 'rejected', 'Solicitud de descanso anual'],
            ];

            // ─── Calcular used/pending days por empleado ──────────────────────────
            $usedByEmployee    = [];
            $pendingByEmployee = [];

            foreach ($vacationDefs as [$idx, $start, $end, $status]) {
                if (! $employees->has($idx)) {
                    continue;
                }

                $empId = $employees[$idx]->id;
                $days  = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;

                if ($status === 'approved') {
                    $usedByEmployee[$empId] = ($usedByEmployee[$empId] ?? 0) + $days;
                } elseif ($status === 'pending') {
                    $pendingByEmployee[$empId] = ($pendingByEmployee[$empId] ?? 0) + $days;
                }
            }

            // ─── Insertar balances ────────────────────────────────────────────────
            $balanceRows = [];

            foreach ($employees as $employee) {
                $hireDate      = $employee->activeContract?->start_date ?? Carbon::now()->subYear();
                $yearsOfService = Carbon::parse($hireDate)->diffInYears(now());

                $entitledDays = match (true) {
                    $yearsOfService >= 10 => 30,
                    $yearsOfService >= 5  => 18,
                    default               => 12,
                };

                $balanceRows[] = [
                    'employee_id'      => $employee->id,
                    'year'             => $year,
                    'years_of_service' => $yearsOfService,
                    'entitled_days'    => $entitledDays,
                    'used_days'        => $usedByEmployee[$employee->id]    ?? 0,
                    'pending_days'     => $pendingByEmployee[$employee->id] ?? 0,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            DB::table('employee_vacation_balances')->insert($balanceRows);

            $balanceMap = DB::table('employee_vacation_balances')
                ->where('year', $year)
                ->pluck('id', 'employee_id');

            // ─── Insertar vacaciones ──────────────────────────────────────────────
            $vacationRows = [];

            foreach ($vacationDefs as [$idx, $start, $end, $status, $reason]) {
                if (! $employees->has($idx)) {
                    continue;
                }

                $employee  = $employees[$idx];
                $startDate = Carbon::parse($start);
                $endDate   = Carbon::parse($end);

                $vacationRows[] = [
                    'employee_id'         => $employee->id,
                    'vacation_balance_id' => $balanceMap[$employee->id] ?? null,
                    'start_date'          => $startDate->toDateString(),
                    'end_date'            => $endDate->toDateString(),
                    'return_date'         => $this->nextWorkingDay($endDate)->toDateString(),
                    'type'                => 'paid',
                    'reason'              => $reason,
                    'status'              => $status,
                    'business_days'       => $this->countBusinessDays($startDate, $endDate),
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }

            if ($vacationRows) {
                DB::table('vacations')->insert($vacationRows);
            }
        });
    }

    /**
     * Cuenta días hábiles (lunes a viernes) entre dos fechas inclusive.
     */
    private function countBusinessDays(Carbon $start, Carbon $end): int
    {
        $days    = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Retorna el primer día hábil siguiente a la fecha dada.
     * Si el día siguiente es sábado o domingo, avanza al lunes.
     */
    private function nextWorkingDay(Carbon $date): Carbon
    {
        $next = $date->copy()->addDay();

        while ($next->isWeekend()) {
            $next->addDay();
        }

        return $next;
    }
}
