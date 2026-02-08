<?php

namespace App\Services;

use App\Models\Aguinaldo;
use App\Models\Employee;
use App\Models\Liquidacion;
use App\Models\LiquidacionItem;
use App\Models\Loan;
use App\Models\Payroll;
use App\Models\VacationBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiquidacionService
{
    protected LiquidacionPDFGenerator $pdfGenerator;

    public function __construct(LiquidacionPDFGenerator $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
    }

    public function calculate(Liquidacion $liquidacion): Liquidacion
    {
        DB::beginTransaction();

        try {
            // Limpiar items previos si se recalcula
            $liquidacion->items()->delete();

            $employee = $liquidacion->employee;
            $terminationDate = Carbon::parse($liquidacion->termination_date);
            $hireDate = Carbon::parse($liquidacion->hire_date);

            $baseSalary = (float) $liquidacion->base_salary;
            $dailySalary = round($baseSalary / 30, 2);

            $yearsOfService = $hireDate->diffInYears($terminationDate);
            $monthsOfService = $hireDate->diffInMonths($terminationDate);
            $daysOfService = $hireDate->diffInDays($terminationDate);

            $averageSalary6m = $this->calculateAverageSalary6Months($employee, $terminationDate);

            $items = [];
            $totalHaberes = 0;
            $totalDeductions = 0;

            // ===== COMPONENTE 1: PREAVISO =====
            $preavisoDays = 0;
            $preavisoAmount = 0;
            if (Liquidacion::includesPreaviso($liquidacion->termination_type) && !$liquidacion->preaviso_otorgado) {
                $preavisoDays = $this->calculatePreavisoDays($yearsOfService);
                $preavisoAmount = round($preavisoDays * $dailySalary, 0);
                $totalHaberes += $preavisoAmount;

                $items[] = [
                    'type' => 'haber',
                    'category' => 'preaviso',
                    'description' => "Preaviso: {$preavisoDays} días x Gs. " . number_format($dailySalary, 0, ',', '.'),
                    'amount' => $preavisoAmount,
                    'metadata' => ['days' => $preavisoDays, 'daily_salary' => $dailySalary],
                ];
            }

            // ===== COMPONENTE 2: INDEMNIZACIÓN =====
            $indemnizacionAmount = 0;
            if (Liquidacion::includesIndemnizacion($liquidacion->termination_type)) {
                $indemnizacionAmount = $this->calculateIndemnizacion($yearsOfService, $monthsOfService, $averageSalary6m);
                $totalHaberes += $indemnizacionAmount;

                $daysPerYear = config('payroll.liquidacion.indemnizacion_days_per_year', 15);
                $items[] = [
                    'type' => 'haber',
                    'category' => 'indemnizacion',
                    'description' => "Indemnización: {$daysPerYear} días/año x {$yearsOfService} años (+ fracción)",
                    'amount' => $indemnizacionAmount,
                    'metadata' => [
                        'years' => $yearsOfService,
                        'months_fraction' => $monthsOfService % 12,
                        'average_salary_6m' => $averageSalary6m,
                        'daily_avg' => round($averageSalary6m / 30, 2),
                    ],
                ];
            }

            // ===== COMPONENTE 3: VACACIONES PROPORCIONALES =====
            [$vacacionesDays, $vacacionesAmount] = $this->calculateVacacionesProporcionales(
                $employee, $terminationDate, $yearsOfService, $dailySalary
            );
            if ($vacacionesAmount > 0) {
                $totalHaberes += $vacacionesAmount;
                $items[] = [
                    'type' => 'haber',
                    'category' => 'vacaciones',
                    'description' => "Vacaciones proporcionales: {$vacacionesDays} días",
                    'amount' => $vacacionesAmount,
                    'metadata' => ['days' => $vacacionesDays, 'daily_salary' => $dailySalary],
                ];
            }

            // ===== COMPONENTE 4: AGUINALDO PROPORCIONAL =====
            $aguinaldoAmount = $this->calculateAguinaldoProporcional($employee, $terminationDate);
            if ($aguinaldoAmount > 0) {
                $totalHaberes += $aguinaldoAmount;
                $monthsInYear = $terminationDate->month;
                $items[] = [
                    'type' => 'haber',
                    'category' => 'aguinaldo',
                    'description' => "Aguinaldo proporcional: {$monthsInYear} meses del año {$terminationDate->year}",
                    'amount' => $aguinaldoAmount,
                    'metadata' => ['months_in_year' => $monthsInYear, 'year' => $terminationDate->year],
                ];
            }

            // ===== COMPONENTE 5: SALARIO PENDIENTE =====
            [$salarioPendienteDays, $salarioPendienteAmount] = $this->calculateSalarioPendiente(
                $employee, $terminationDate, $dailySalary
            );
            if ($salarioPendienteAmount > 0) {
                $totalHaberes += $salarioPendienteAmount;
                $items[] = [
                    'type' => 'haber',
                    'category' => 'salario_pendiente',
                    'description' => "Salario pendiente: {$salarioPendienteDays} días del mes",
                    'amount' => $salarioPendienteAmount,
                    'metadata' => ['days' => $salarioPendienteDays, 'daily_salary' => $dailySalary],
                ];
            }

            // ===== DEDUCCIONES =====

            // IPS 9% sobre salario pendiente + vacaciones (preaviso/indemnización/aguinaldo exentos)
            $ipsRate = config('payroll.liquidacion.ips_employee_rate', 9);
            $ipsBase = $salarioPendienteAmount + $vacacionesAmount;
            $ipsDeduction = round($ipsBase * ($ipsRate / 100), 0);
            if ($ipsDeduction > 0) {
                $totalDeductions += $ipsDeduction;
                $items[] = [
                    'type' => 'deduction',
                    'category' => 'ips',
                    'description' => "Aporte IPS Obrero ({$ipsRate}%)",
                    'amount' => $ipsDeduction,
                    'metadata' => ['base' => $ipsBase, 'rate' => $ipsRate],
                ];
            }

            // Préstamos pendientes
            $loanDeduction = $this->calculatePendingLoans($employee);
            if ($loanDeduction > 0) {
                $totalDeductions += $loanDeduction;
                $items[] = [
                    'type' => 'deduction',
                    'category' => 'loan',
                    'description' => 'Saldo de préstamos/adelantos pendientes',
                    'amount' => $loanDeduction,
                    'metadata' => ['loan_ids' => $this->getPendingLoanIds($employee)],
                ];
            }

            $netAmount = $totalHaberes - $totalDeductions;

            // Persistir items
            foreach ($items as $item) {
                LiquidacionItem::create(array_merge($item, [
                    'liquidacion_id' => $liquidacion->id,
                ]));
            }

            // Actualizar liquidación
            $liquidacion->update([
                'daily_salary' => $dailySalary,
                'years_of_service' => $yearsOfService,
                'months_of_service' => $monthsOfService,
                'days_of_service' => $daysOfService,
                'average_salary_6m' => $averageSalary6m,
                'preaviso_days' => $preavisoDays,
                'preaviso_amount' => $preavisoAmount,
                'indemnizacion_amount' => $indemnizacionAmount,
                'vacaciones_days' => $vacacionesDays,
                'vacaciones_amount' => $vacacionesAmount,
                'aguinaldo_proporcional_amount' => $aguinaldoAmount,
                'salario_pendiente_days' => $salarioPendienteDays,
                'salario_pendiente_amount' => $salarioPendienteAmount,
                'ips_deduction' => $ipsDeduction,
                'loan_deduction' => $loanDeduction,
                'total_haberes' => $totalHaberes,
                'total_deductions' => $totalDeductions,
                'net_amount' => $netAmount,
                'status' => 'calculated',
                'calculated_at' => now(),
            ]);

            // Generar PDF
            $pdfPath = $this->pdfGenerator->generate($liquidacion->fresh());
            $liquidacion->update(['pdf_path' => $pdfPath]);

            DB::commit();
            return $liquidacion->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al calcular liquidación: ' . $e->getMessage(), [
                'liquidacion_id' => $liquidacion->id,
                'employee_id' => $liquidacion->employee_id,
            ]);
            throw $e;
        }
    }

    public function close(Liquidacion $liquidacion): void
    {
        DB::transaction(function () use ($liquidacion) {
            $liquidacion->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $liquidacion->employee->update(['status' => 'inactive']);

            $this->cancelPendingLoans($liquidacion->employee);
        });
    }

    protected function calculatePreavisoDays(int $yearsOfService): int
    {
        $tiers = config('payroll.liquidacion.preaviso_tiers', []);

        foreach ($tiers as $tier) {
            $min = $tier['min_years'];
            $max = $tier['max_years'];

            if ($max === null && $yearsOfService >= $min) {
                return $tier['days'];
            }

            if ($yearsOfService >= $min && $yearsOfService < $max) {
                return $tier['days'];
            }
        }

        return 30; // fallback
    }

    protected function calculateIndemnizacion(int $years, int $totalMonths, float $avgSalary6m): float
    {
        $daysPerYear = config('payroll.liquidacion.indemnizacion_days_per_year', 15);
        $dailyAvg = $avgSalary6m / 30;

        // Años completos
        $fullYearsDays = $years * $daysPerYear;

        // Fracción proporcional por meses restantes
        $remainingMonths = $totalMonths - ($years * 12);
        $fractionDays = round(($remainingMonths / 12) * $daysPerYear, 2);

        $totalDays = $fullYearsDays + $fractionDays;

        return round($totalDays * $dailyAvg, 0);
    }

    protected function calculateAverageSalary6Months(Employee $employee, Carbon $terminationDate): float
    {
        $sixMonthsAgo = $terminationDate->copy()->subMonths(6)->startOfMonth();

        $payrolls = Payroll::where('employee_id', $employee->id)
            ->whereHas('period', function ($q) use ($sixMonthsAgo, $terminationDate) {
                $q->where('start_date', '>=', $sixMonthsAgo)
                  ->where('start_date', '<=', $terminationDate);
            })
            ->get();

        if ($payrolls->isEmpty()) {
            return (float) $employee->base_salary;
        }

        $totalGross = $payrolls->sum('gross_salary');
        return round($totalGross / $payrolls->count(), 2);
    }

    protected function calculateVacacionesProporcionales(
        Employee $employee,
        Carbon $terminationDate,
        int $yearsOfService,
        float $dailySalary
    ): array {
        $entitledDays = $this->getEntitledVacationDays($yearsOfService);

        if ($entitledDays === 0) {
            return [0, 0];
        }

        // Obtener balance del año actual
        $currentYear = $terminationDate->year;
        $balance = VacationBalance::where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->first();

        $usedDays = $balance?->used_days ?? 0;

        // Calcular días proporcionales según meses trabajados en el período
        $anniversaryDate = $employee->hire_date->copy()->year($currentYear);
        if ($anniversaryDate->gt($terminationDate)) {
            $anniversaryDate->subYear();
        }
        $monthsInPeriod = $anniversaryDate->diffInMonths($terminationDate);
        $proportionalDays = round(($entitledDays * $monthsInPeriod) / 12);

        $unusedDays = max(0, $proportionalDays - $usedDays);
        $amount = round($unusedDays * $dailySalary, 0);

        return [$unusedDays, $amount];
    }

    protected function getEntitledVacationDays(int $yearsOfService): int
    {
        if ($yearsOfService < 1) {
            return 0;
        }

        $tiers = config('payroll.vacation.tiers', []);

        foreach ($tiers as $tier) {
            $min = $tier['min_years'];
            $max = $tier['max_years'];

            if ($max === null && $yearsOfService >= $min) {
                return $tier['days'];
            }

            if ($yearsOfService >= $min && $yearsOfService < $max) {
                return $tier['days'];
            }
        }

        return 12; // fallback
    }

    protected function calculateAguinaldoProporcional(Employee $employee, Carbon $terminationDate): float
    {
        $year = $terminationDate->year;

        // Verificar si ya se pagó aguinaldo del año
        $aguinaldoPagado = Aguinaldo::where('employee_id', $employee->id)
            ->whereHas('period', fn($q) => $q->where('year', $year))
            ->exists();

        if ($aguinaldoPagado) {
            return 0;
        }

        // Sumar payrolls del año hasta la fecha de terminación
        $payrolls = Payroll::where('employee_id', $employee->id)
            ->whereHas('period', function ($q) use ($year, $terminationDate) {
                $q->whereYear('start_date', $year)
                  ->where('start_date', '<=', $terminationDate);
            })
            ->get();

        $totalEarned = $payrolls->sum(fn($p) => (float) $p->base_salary + (float) $p->total_perceptions);

        return round($totalEarned / 12, 0);
    }

    protected function calculateSalarioPendiente(Employee $employee, Carbon $terminationDate, float $dailySalary): array
    {
        // Verificar si ya se pagó el mes actual
        $hasPayroll = Payroll::where('employee_id', $employee->id)
            ->whereHas('period', function ($q) use ($terminationDate) {
                $q->where('end_date', '>=', $terminationDate->copy()->startOfMonth());
            })
            ->exists();

        if ($hasPayroll) {
            return [0, 0];
        }

        $daysWorked = $terminationDate->day;
        $amount = round($daysWorked * $dailySalary, 0);

        return [$daysWorked, $amount];
    }

    protected function calculatePendingLoans(Employee $employee): float
    {
        return Loan::getTotalActiveDebtForEmployee($employee->id);
    }

    protected function getPendingLoanIds(Employee $employee): array
    {
        return Loan::where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'pending'])
            ->pluck('id')
            ->toArray();
    }

    protected function cancelPendingLoans(Employee $employee): void
    {
        $activeLoans = Loan::where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'pending'])
            ->get();

        foreach ($activeLoans as $loan) {
            $loan->installments()->where('status', 'pending')->update([
                'status' => 'cancelled',
                'notes' => 'Cancelado por liquidación/finiquito',
            ]);
            $loan->update([
                'status' => 'cancelled',
                'notes' => ($loan->notes ? $loan->notes . "\n\n" : '')
                    . 'Cancelado por liquidación. Saldo deducido de la liquidación.',
            ]);
        }
    }
}
