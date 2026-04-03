<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\LoanInstallment;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PayrollService
{
    protected PerceptionCalculator $perceptionCalculator;
    protected DeductionCalculator $deductionCalculator;
    protected ExtraHourCalculator $extraHourCalculator;
    protected AbsencePenaltyCalculator $absencePenaltyCalculator;
    protected LoanInstallmentCalculator $loanInstallmentCalculator;
    protected FamilyBonusCalculator $familyBonusCalculator;
    protected PayrollPDFGenerator $payrollPDFGenerator;

    public function __construct(
        PerceptionCalculator $perceptionCalculator,
        DeductionCalculator $deductionCalculator,
        ExtraHourCalculator $extraHourCalculator,
        AbsencePenaltyCalculator $absencePenaltyCalculator,
        LoanInstallmentCalculator $loanInstallmentCalculator,
        FamilyBonusCalculator $familyBonusCalculator,
        PayrollPDFGenerator $payrollPDFGenerator
    ) {
        $this->perceptionCalculator = $perceptionCalculator;
        $this->deductionCalculator = $deductionCalculator;
        $this->extraHourCalculator = $extraHourCalculator;
        $this->absencePenaltyCalculator = $absencePenaltyCalculator;
        $this->loanInstallmentCalculator = $loanInstallmentCalculator;
        $this->familyBonusCalculator = $familyBonusCalculator;
        $this->payrollPDFGenerator = $payrollPDFGenerator;
    }

    public function generateForPeriod(PayrollPeriod $period): int
    {
        // Validar estado del período
        if (!in_array($period->status, ['draft', 'processing'])) {
            throw new \InvalidArgumentException(
                "No se pueden generar recibos para un período con estado '{$period->status}'. Solo se permiten períodos en 'borrador' o 'en proceso'."
            );
        }

        $count = 0;

        $employees = Employee::query()
            ->where('status', 'active')
            ->whereHas('activeContract', fn ($q) =>
                $q->where('payroll_type', $period->frequency)->whereNotNull('salary')
            )
            ->with('activeContract')
            ->get();

        foreach ($employees as $employee) {
            // Evitar duplicados
            if (Payroll::where('employee_id', $employee->id)
                ->where('payroll_period_id', $period->id)
                ->exists()
            ) {
                continue;
            }

            DB::beginTransaction();

            try {
                // Calcular salario base según tipo de empleo
                if ($employee->employment_type === 'day_laborer') {
                    $workedDays = $employee->attendanceDays()
                        ->whereBetween('date', [$period->start_date, $period->end_date])
                        ->where('status', 'present')
                        ->count();

                    if ($workedDays === 0) {
                        Log::info('Jornalero sin días trabajados, omitiendo recibo', [
                            'employee_id' => $employee->id,
                            'period_id' => $period->id,
                        ]);
                        DB::rollBack();
                        continue;
                    }

                    $baseSalary = round($employee->daily_rate * $workedDays, 2);

                    Log::info('Jornalero: cálculo de salario base', [
                        'employee_id' => $employee->id,
                        'daily_rate' => $employee->daily_rate,
                        'worked_days' => $workedDays,
                        'base_salary' => $baseSalary,
                    ]);
                } else {
                    $baseSalary = $employee->base_salary;
                }

                // Cálculo modular — extras antes de deducciones para obtener la base IPS correcta
                $perceptions      = $this->perceptionCalculator->calculate($employee, $period);
                $extras           = $this->extraHourCalculator->calculate($employee, $period);
                $ipsBase          = $baseSalary + $perceptions['ips_total'] + $extras['total'];
                $deductions       = $this->deductionCalculator->calculate($employee, $period, $ipsBase);
                $absences         = $this->absencePenaltyCalculator->calculate($employee, $period);
                $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);
                $familyBonus      = $this->familyBonusCalculator->calculate($employee, $period);

                $totalPerceptions = $perceptions['total'] + $extras['total'] + $familyBonus['total'];
                // Percepciones que computan para IPS y aguinaldo (salariales + HE; bonificación familiar excluida)
                $ipsPerceptions   = $perceptions['ips_total'] + $extras['total'];
                $totalDeductions  = $deductions['total'] + $absences['total'] + $loanInstallments['total'];
                $netSalary        = $baseSalary + $totalPerceptions - $totalDeductions;

                $payroll = Payroll::create([
                    'employee_id'       => $employee->id,
                    'payroll_period_id' => $period->id,
                    'base_salary'       => $baseSalary,
                    'total_perceptions' => $totalPerceptions,
                    'ips_perceptions'   => $ipsPerceptions,
                    'total_deductions'  => $totalDeductions,
                    'net_salary'        => $netSalary,
                    'gross_salary'      => $baseSalary + $totalPerceptions,
                    'generated_at'      => now(),
                    'status'            => 'draft',
                ]);

                // Ítems: percepciones (incluyendo bonificación familiar si aplica)
                foreach (array_merge($perceptions['items'], $extras['items'], $familyBonus['items']) as $item) {
                    PayrollItem::create([
                        'payroll_id'      => $payroll->id,
                        'type'            => 'perception',
                        'perception_type' => $item['perception_type'] ?? null,
                        'description'     => $item['description'],
                        'amount'          => $item['amount'],
                    ]);
                }

                // Ítems: deducciones (incluye cuotas de préstamos/adelantos)
                foreach (array_merge($deductions['items'], $absences['items'], $loanInstallments['items']) as $item) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'deduction',
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                // Marcar cuotas de préstamos como pagadas
                if ($loanInstallments['installments']->isNotEmpty()) {
                    $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                    $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds);
                }

                // Generar PDF
                $pdfPath = $this->payrollPDFGenerator->generate($payroll);
                $payroll->update(['pdf_path' => $pdfPath]);

                DB::commit();

                Log::info('Recibo de nómina generado', [
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'period_id' => $period->id,
                    'base_salary' => $baseSalary,
                    'net_salary' => $netSalary,
                ]);

                $count++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Error al generar recibo: ' . $e->getMessage(), [
                    'employee_id' => $employee->id,
                    'period_id' => $period->id,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
        return $count;
    }

    public function generateForEmployee(Employee $employee, PayrollPeriod $period): Payroll
    {
        if (!in_array($period->status, ['draft', 'processing'])) {
            throw new \InvalidArgumentException(
                "No se pueden generar recibos para un período con estado '{$period->status}'. Solo se permiten períodos en borrador o en proceso."
            );
        }

        if (Payroll::where('employee_id', $employee->id)
            ->where('payroll_period_id', $period->id)
            ->exists()
        ) {
            throw new \InvalidArgumentException(
                "Ya existe un recibo para este empleado en el período seleccionado."
            );
        }

        $employee->load('activeContract');

        if (!$employee->activeContract) {
            throw new \InvalidArgumentException(
                "El empleado no tiene un contrato activo."
            );
        }

        if ($employee->activeContract->payroll_type !== $period->frequency) {
            throw new \InvalidArgumentException(
                "El tipo de nómina del contrato del empleado ({$employee->activeContract->payroll_type}) no coincide con la frecuencia del período ({$period->frequency})."
            );
        }

        DB::beginTransaction();

        try {
            if ($employee->employment_type === 'day_laborer') {
                $workedDays = $employee->attendanceDays()
                    ->whereBetween('date', [$period->start_date, $period->end_date])
                    ->where('status', 'present')
                    ->count();

                if ($workedDays === 0) {
                    throw new \InvalidArgumentException(
                        "El empleado jornalero no tiene días trabajados registrados en este período."
                    );
                }

                $baseSalary = round($employee->daily_rate * $workedDays, 2);
            } else {
                $baseSalary = $employee->base_salary;
            }

            $perceptions      = $this->perceptionCalculator->calculate($employee, $period);
            $extras           = $this->extraHourCalculator->calculate($employee, $period);
            $ipsBase          = $baseSalary + $perceptions['ips_total'] + $extras['total'];
            $deductions       = $this->deductionCalculator->calculate($employee, $period, $ipsBase);
            $absences         = $this->absencePenaltyCalculator->calculate($employee, $period);
            $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);
            $familyBonus      = $this->familyBonusCalculator->calculate($employee, $period);

            $totalPerceptions = $perceptions['total'] + $extras['total'] + $familyBonus['total'];
            $ipsPerceptions   = $perceptions['ips_total'] + $extras['total'];
            $totalDeductions  = $deductions['total'] + $absences['total'] + $loanInstallments['total'];
            $netSalary        = $baseSalary + $totalPerceptions - $totalDeductions;

            $payroll = Payroll::create([
                'employee_id'       => $employee->id,
                'payroll_period_id' => $period->id,
                'base_salary'       => $baseSalary,
                'total_perceptions' => $totalPerceptions,
                'ips_perceptions'   => $ipsPerceptions,
                'total_deductions'  => $totalDeductions,
                'gross_salary'      => $baseSalary + $totalPerceptions,
                'net_salary'        => $netSalary,
                'generated_at'      => now(),
                'status'            => 'draft',
            ]);

            foreach (array_merge($perceptions['items'], $extras['items'], $familyBonus['items']) as $item) {
                PayrollItem::create([
                    'payroll_id'      => $payroll->id,
                    'type'            => 'perception',
                    'perception_type' => $item['perception_type'] ?? null,
                    'description'     => $item['description'],
                    'amount'          => $item['amount'],
                ]);
            }

            foreach (array_merge($deductions['items'], $absences['items'], $loanInstallments['items']) as $item) {
                PayrollItem::create([
                    'payroll_id'  => $payroll->id,
                    'type'        => 'deduction',
                    'description' => $item['description'],
                    'amount'      => $item['amount'],
                ]);
            }

            if ($loanInstallments['installments']->isNotEmpty()) {
                $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds);
            }

            $pdfPath = $this->payrollPDFGenerator->generate($payroll);
            $payroll->update(['pdf_path' => $pdfPath]);

            DB::commit();

            Log::info('Recibo de nómina generado manualmente', [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'period_id'  => $period->id,
                'net_salary' => $netSalary,
            ]);

            return $payroll->refresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al generar recibo manualmente: ' . $e->getMessage(), [
                'employee_id' => $employee->id,
                'period_id'  => $period->id,
                'trace'      => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function regenerateForEmployee(Payroll $payroll): Payroll
    {
        // Validar que la nómina no esté aprobada o pagada
        if (in_array($payroll->status, ['approved', 'paid'])) {
            throw new \InvalidArgumentException(
                "No se puede regenerar una nómina con estado '{$payroll->status}'."
            );
        }

        $employee = $payroll->employee->load('activeContract');
        $period = $payroll->period;

        DB::beginTransaction();

        try {
            // Revertir cuotas de préstamo pagadas del período anterior
            LoanInstallment::whereHas('loan', fn($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', 'active'))
                ->where('status', 'paid')
                ->whereBetween('due_date', [$period->start_date, $period->end_date])
                ->update(['status' => 'pending', 'paid_at' => null]);

            // Eliminar ítems existentes
            $payroll->items()->delete();

            // Eliminar PDF anterior
            if ($payroll->pdf_path && Storage::disk('public')->exists($payroll->pdf_path)) {
                Storage::disk('public')->delete($payroll->pdf_path);
            }

            // Calcular salario base según tipo de empleo
            if ($employee->employment_type === 'day_laborer') {
                $workedDays = $employee->attendanceDays()
                    ->whereBetween('date', [$period->start_date, $period->end_date])
                    ->where('status', 'present')
                    ->count();

                $baseSalary = round($employee->daily_rate * $workedDays, 2);
            } else {
                $baseSalary = $employee->base_salary;
            }

            // Recalcular con los 6 calculadores — extras antes de deducciones para base IPS correcta
            $perceptions      = $this->perceptionCalculator->calculate($employee, $period);
            $extras           = $this->extraHourCalculator->calculate($employee, $period);
            $ipsBase          = $baseSalary + $perceptions['ips_total'] + $extras['total'];
            $deductions       = $this->deductionCalculator->calculate($employee, $period, $ipsBase);
            $absences         = $this->absencePenaltyCalculator->calculate($employee, $period);
            $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);
            $familyBonus      = $this->familyBonusCalculator->calculate($employee, $period);

            $totalPerceptions = $perceptions['total'] + $extras['total'] + $familyBonus['total'];
            $ipsPerceptions   = $perceptions['ips_total'] + $extras['total'];
            $totalDeductions  = $deductions['total'] + $absences['total'] + $loanInstallments['total'];
            $netSalary        = $baseSalary + $totalPerceptions - $totalDeductions;

            // Actualizar el registro existente
            $payroll->update([
                'base_salary'       => $baseSalary,
                'total_perceptions' => $totalPerceptions,
                'ips_perceptions'   => $ipsPerceptions,
                'total_deductions'  => $totalDeductions,
                'gross_salary'      => $baseSalary + $totalPerceptions,
                'net_salary'        => $netSalary,
                'generated_at'      => now(),
            ]);

            // Recrear ítems: percepciones (incluyendo bonificación familiar si aplica)
            foreach (array_merge($perceptions['items'], $extras['items'], $familyBonus['items']) as $item) {
                PayrollItem::create([
                    'payroll_id'      => $payroll->id,
                    'type'            => 'perception',
                    'perception_type' => $item['perception_type'] ?? null,
                    'description'     => $item['description'],
                    'amount'          => $item['amount'],
                ]);
            }

            // Recrear ítems: deducciones
            foreach (array_merge($deductions['items'], $absences['items'], $loanInstallments['items']) as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'deduction',
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            // Marcar cuotas de préstamos como pagadas
            if ($loanInstallments['installments']->isNotEmpty()) {
                $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds);
            }

            // Regenerar PDF
            $pdfPath = $this->payrollPDFGenerator->generate($payroll);
            $payroll->update(['pdf_path' => $pdfPath]);

            DB::commit();

            Log::info('Recibo de nómina regenerado', [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'period_id' => $period->id,
                'net_salary' => $netSalary,
            ]);

            return $payroll->refresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al regenerar recibo: ' . $e->getMessage(), [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
