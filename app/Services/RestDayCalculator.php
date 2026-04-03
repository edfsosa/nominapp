<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Calcula el descanso semanal remunerado para trabajadores a jornal (Art. 218 CLT).
 *
 * El trabajador jornalero tiene derecho a un día de descanso remunerado por cada
 * semana trabajada. El valor se calcula por semana ISO:
 *
 *   descanso_semana = min(días_presentes, 6) × tarifa_diaria / 6
 *
 * El cap en 6 días evita doble pago cuando el empleado trabajó el domingo
 * (ese día ya viaja como HE feriado en ExtraHourCalculator).
 *
 * Para empleados mensualados retorna total = 0 (el descanso ya está incluido
 * en el salario mensual).
 *
 * El descanso es remuneración salarial: computa para IPS y aguinaldo.
 */
class RestDayCalculator
{
    /**
     * Calcula el descanso semanal remunerado del período.
     *
     * @param  Employee      $employee
     * @param  PayrollPeriod $period
     * @return array{total: float, items: array}
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $emptyResult = ['total' => 0.0, 'items' => []];

        if ($employee->employment_type !== 'day_laborer') {
            return $emptyResult;
        }

        $dailyRate = (float) ($employee->daily_rate ?? 0);

        if ($dailyRate <= 0) {
            Log::warning('RestDayCalculator: jornalero sin tarifa diaria', [
                'employee_id' => $employee->id,
            ]);
            return $emptyResult;
        }

        // Obtener los días con presencia en el período, con su fecha para agrupar por semana ISO
        $presentDates = $employee->attendanceDays()
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('status', 'present')
            ->pluck('date');

        if ($presentDates->isEmpty()) {
            return $emptyResult;
        }

        // Agrupar por semana ISO (año-semana) y contar días presentes por semana
        $byWeek = $presentDates
            ->groupBy(fn($date) => Carbon::parse($date)->format('o-W')); // o = año ISO, W = semana ISO

        $total = 0.0;

        foreach ($byWeek as $weekKey => $dates) {
            $daysWorked  = min($dates->count(), 6); // cap: máx 6 para no duplicar con HE feriado
            $restValue   = round($daysWorked * $dailyRate / 6, 2);
            $total      += $restValue;
        }

        $total = round($total, 2);

        Log::info('RestDayCalculator: descanso semanal calculado', [
            'employee_id' => $employee->id,
            'period_id'   => $period->id,
            'weeks'       => $byWeek->count(),
            'total'       => $total,
        ]);

        return [
            'total' => $total,
            'items' => [
                [
                    'description'    => 'Descanso Semanal Remunerado',
                    'amount'         => $total,
                    'perception_type' => 'salary',
                ],
            ],
        ];
    }
}
