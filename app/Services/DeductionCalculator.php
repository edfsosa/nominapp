<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;
use Illuminate\Support\Facades\Log;

class DeductionCalculator
{
    /**
     * Calcula el total de deducciones activas del empleado en el período.
     *
     * @param  Employee      $employee
     * @param  PayrollPeriod $period
     * @param  float|null    $ipsBase  Base imponible IPS (base_salary + ips_perceptions).
     *                                 Si se provee, la deducción IPS la usa en lugar del salario contractual.
     *                                 Si es null, todas las deducciones usan el salario contractual del empleado.
     * @return array{total: float, items: array}
     */
    public function calculate(Employee $employee, PayrollPeriod $period, ?float $ipsBase = null): array
    {
        $items = [];
        $total = 0;

        $ipsCode = $ipsBase !== null
            ? app(PayrollSettings::class)->ips_deduction_code
            : null;

        $assignments = $employee->employeeDeductions()
            ->forPeriod($period->start_date, $period->end_date)
            ->with('deduction')
            ->get();

        foreach ($assignments as $assignment) {
            $deduction    = $assignment->deduction;
            $customAmount = $assignment->custom_amount;
            $salaryBase   = 0;

            if ($customAmount === null && $deduction->isPercentage()) {
                // La deducción IPS se aplica sobre la base IPS completa (salario + percepciones salariales + HE).
                // El resto de deducciones porcentuales usan el salario contractual base.
                if ($ipsCode !== null && $deduction->code === $ipsCode) {
                    $salaryBase = (int) round($ipsBase);
                } else {
                    $salaryBase = $employee->employment_type === 'day_laborer'
                        ? (int) ($employee->daily_rate ?? 0)
                        : (int) ($employee->base_salary ?? 0);
                }

                if ($salaryBase <= 0) {
                    Log::warning('DeductionCalculator: porcentaje sobre salario base inválido', [
                        'employee_id'     => $employee->id,
                        'deduction'       => $deduction->name,
                        'salary_base'     => $salaryBase,
                        'employment_type' => $employee->employment_type,
                    ]);

                    $items[] = ['description' => $deduction->name, 'amount' => 0];
                    continue;
                }
            }

            $amount = $deduction->calculateAmount($salaryBase, $customAmount);
            $total += $amount;

            $items[] = [
                'description' => $deduction->name,
                'amount'      => $amount,
            ];
        }

        return [
            'total' => $total,
            'items' => $items,
        ];
    }
}
