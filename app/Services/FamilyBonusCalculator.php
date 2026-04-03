<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;

/**
 * Calcula la bonificación familiar según Arts. 253-262 del Código del Trabajo (Ley 213/93).
 *
 * Reglas:
 *  - Solo aplica a empleados con hijos registrados (children_count > 0).
 *  - Solo aplica si el salario base del contrato no supera dos salarios mínimos mensuales.
 *  - Monto por hijo = min_salary_monthly × (family_bonus_percentage / 100).
 *  - No es percepción salarial: no computa para IPS ni aguinaldo.
 */
class FamilyBonusCalculator
{
    /**
     * Calcula la bonificación familiar para un empleado en un período dado.
     *
     * @param  Employee      $employee
     * @param  PayrollPeriod $period   No se usa para el cálculo pero mantiene la firma uniforme.
     * @return array{total: float, items: array}
     */
    public function calculate(Employee $employee, PayrollPeriod $period): array
    {
        $emptyResult = ['total' => 0.0, 'items' => []];

        $childrenCount = (int) $employee->children_count;

        if ($childrenCount <= 0) {
            return $emptyResult;
        }

        $settings = app(PayrollSettings::class);

        $minSalary = $settings->min_salary_monthly;

        // Tope legal: dos salarios mínimos (Art. 254 CLT)
        $salaryBase = (float) ($employee->activeContract?->salary ?? 0);
        if ($salaryBase > $minSalary * 2) {
            return $emptyResult;
        }

        $bonusPerChild = round($minSalary * ($settings->family_bonus_percentage / 100), 2);
        $total         = round($bonusPerChild * $childrenCount, 2);

        $label = $childrenCount === 1
            ? 'Bonificación Familiar (1 hijo)'
            : "Bonificación Familiar ({$childrenCount} hijos)";

        return [
            'total' => $total,
            'items' => [
                ['description' => $label, 'amount' => $total],
            ],
        ];
    }
}
