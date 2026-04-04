<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;
use Illuminate\Support\Facades\Log;

/**
 * Calcula el descuento por minutos de tardanza aprobados por RR.HH.
 *
 * Solo procesa días con tardiness_deduction_approved = true.
 * Las ausencias injustificadas se gestionan a través del flujo de revisión
 * de RR.HH. en AbsenceResource, que genera un EmployeeDeduction con código AUS-INJ
 * recogido posteriormente por DeductionCalculator.
 */
class AbsencePenaltyCalculator
{
    /**
     * Calcula el total a descontar por tardanzas aprobadas en el período.
     *
     * @param  Employee      $employee
     * @param  PayrollPeriod $period
     * @return array{total: float, minutes: int, items: array}
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $emptyResult = ['total' => 0.0, 'minutes' => 0, 'items' => []];

        // Para jornaleros no aplica: trabajan y cobran por día presente, no por horas.
        if ($employee->employment_type === 'day_laborer') {
            return $emptyResult;
        }

        if (!$employee->base_salary || $employee->base_salary <= 0) {
            Log::warning('AbsencePenaltyCalculator: empleado sin salario base válido', [
                'employee_id' => $employee->id,
                'base_salary' => $employee->base_salary,
            ]);
            return $emptyResult;
        }

        $settings = app(PayrollSettings::class);
        $monthlyHours = $settings->monthly_hours;

        if ($monthlyHours <= 0) {
            Log::warning('AbsencePenaltyCalculator: configuración de horas inválida', [
                'employee_id'  => $employee->id,
                'monthly_hours' => $monthlyHours,
            ]);
            return $emptyResult;
        }

        $tardinessMinutes = (int) $employee->attendanceDays()
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->where('late_minutes', '>', 0)
            ->where('tardiness_deduction_approved', true)
            ->sum('late_minutes');

        if ($tardinessMinutes === 0) {
            return $emptyResult;
        }

        $hourlyRate = $employee->base_salary / $monthlyHours;
        $total      = round(($tardinessMinutes / 60) * $hourlyRate, 2);

        return [
            'total'   => $total,
            'minutes' => $tardinessMinutes,
            'items'   => [[
                'description' => "Descuento por Tardanzas ({$tardinessMinutes} min)",
                'amount'      => $total,
            ]],
        ];
    }
}
