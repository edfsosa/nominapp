<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\LoanInstallment;
use App\Models\MerchandiseWithdrawalInstallment;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PayrollService
{
    public function __construct(
        protected PerceptionCalculator $perceptionCalculator,
        protected DeductionCalculator $deductionCalculator,
        protected ExtraHourCalculator $extraHourCalculator,
        protected AbsencePenaltyCalculator $absencePenaltyCalculator,
        protected LoanInstallmentCalculator $loanInstallmentCalculator,
        protected AdvanceCalculator $advanceCalculator,
        protected MerchandiseInstallmentCalculator $merchandiseInstallmentCalculator,
        protected FamilyBonusCalculator $familyBonusCalculator,
        protected RestDayCalculator $restDayCalculator,
        protected PayrollPDFGenerator $payrollPDFGenerator,
    ) {}

    public function generateForPeriod(PayrollPeriod $period): int
    {
        // Validar estado del período
        if (! in_array($period->status, ['draft', 'processing'])) {
            throw new \InvalidArgumentException(
                "No se pueden generar recibos para un período con estado '{$period->status}'. Solo se permiten períodos en 'borrador' o 'en proceso'."
            );
        }

        $count = 0;

        $employees = Employee::query()
            ->where('status', 'active')
            ->whereHas('activeContract', fn ($q) => $q->where('payroll_type', $period->frequency)->whereNotNull('salary'))
            ->when($period->company_id, fn ($q) => $q->whereHas('branch', fn ($q) => $q->where('company_id', $period->company_id)))
            ->with('activeContract')
            ->get();

        foreach ($employees as $employee) {
            // Evitar duplicados. Si existe uno soft-deleted, eliminarlo permanentemente
            // para liberar la unique constraint (employee_id, payroll_period_id).
            $existing = Payroll::withTrashed()
                ->where('employee_id', $employee->id)
                ->where('payroll_period_id', $period->id)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->forceDelete();
                } else {
                    continue;
                }
            }

            // Calcular salario base antes de abrir la transacción para evitar rollbacks vacíos
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

            DB::beginTransaction();

            try {
                // Cálculo modular — extras antes de deducciones para obtener la base IPS correcta.
                // Préstamos y adelantos se calculan ANTES de DeductionCalculator para que
                // los EmployeeDeduction creados sean recogidos en el mismo ciclo.
                $perceptions = $this->perceptionCalculator->calculate($employee, $period);
                $extras = $this->extraHourCalculator->calculate($employee, $period);
                $restDay = $this->restDayCalculator->calculate($employee, $period);
                $ipsBase = $baseSalary + $perceptions['ips_total'] + $extras['total'] + $restDay['total'];
                $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);
                $advances = $this->advanceCalculator->calculate($employee, $period);
                $merchandiseInstallments = $this->merchandiseInstallmentCalculator->calculate($employee, $period);
                $deductions = $this->deductionCalculator->calculate($employee, $period, $ipsBase);
                $absences = $this->absencePenaltyCalculator->calculate($employee, $period);
                $familyBonus = $this->familyBonusCalculator->calculate($employee, $period);

                // Remuneración vacacional para vacaciones con payment_method=with_payroll cuyo
                // start_date cae dentro del período. No suma a la base IPS (Art. 218 CLT).
                $vacationPays = $this->resolveVacationPays($employee, $period);

                $totalPerceptions = $perceptions['total'] + $extras['total'] + $restDay['total'] + $familyBonus['total'] + $vacationPays['total'];
                // Percepciones que computan para IPS y aguinaldo (salariales + HE + descanso; bonificación familiar y vacacional excluidas)
                $ipsPerceptions = $perceptions['ips_total'] + $extras['total'] + $restDay['total'];
                $totalDeductions = $deductions['total'] + $absences['total'];
                $netSalary = $baseSalary + $totalPerceptions - $totalDeductions;

                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'base_salary' => $baseSalary,
                    'total_perceptions' => $totalPerceptions,
                    'ips_perceptions' => $ipsPerceptions,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'gross_salary' => $baseSalary + $totalPerceptions,
                    'generated_at' => now(),
                    'status' => 'draft',
                    'payment_method' => $employee->activeContract?->payment_method === 'cash' ? 'cash' : 'transfer',
                ]);

                // Ítems: percepciones (incluyendo descanso remunerado y bonificación familiar si aplican)
                foreach (array_merge($perceptions['items'], $extras['items'], $restDay['items'], $familyBonus['items']) as $item) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'perception',
                        'perception_type' => $item['perception_type'] ?? null,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                // Ítems: remuneración vacacional with_payroll
                foreach ($vacationPays['items'] as $item) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'perception',
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                // Ítems: deducciones (las cuotas de préstamos van incluidas en $deductions vía EmployeeDeduction)
                foreach (array_merge($deductions['items'], $absences['items']) as $item) {
                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'type' => 'deduction',
                        'deduction_type' => $item['deduction_type'] ?? null,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                // Marcar cuotas de préstamos como pagadas
                if ($loanInstallments['installments']->isNotEmpty()) {
                    $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                    $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds, $payroll->id);
                }

                // Marcar adelantos como pagados
                if ($advances['advances']->isNotEmpty()) {
                    $advanceIds = $advances['advances']->pluck('id')->toArray();
                    $this->advanceCalculator->markAdvancesAsPaid($advanceIds, $payroll->id);
                }

                // Marcar cuotas de mercadería como pagadas
                if ($merchandiseInstallments['installments']->isNotEmpty()) {
                    $merchandiseIds = $merchandiseInstallments['installments']->pluck('id')->toArray();
                    $this->merchandiseInstallmentCalculator->markInstallmentsAsPaid($merchandiseIds, $payroll->id);
                }

                // Marcar remuneraciones vacacionales como pagadas
                foreach ($vacationPays['vacations'] as $vacation) {
                    VacationService::recordPayment($vacation, now());
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
                Log::error('Error al generar recibo: '.$e->getMessage(), [
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
        if (! in_array($period->status, ['draft', 'processing'])) {
            throw new \InvalidArgumentException(
                "No se pueden generar recibos para un período con estado '{$period->status}'. Solo se permiten períodos en borrador o en proceso."
            );
        }

        if (Payroll::where('employee_id', $employee->id)
            ->where('payroll_period_id', $period->id)
            ->exists()
        ) {
            throw new \InvalidArgumentException(
                'Ya existe un recibo para este empleado en el período seleccionado.'
            );
        }

        $employee->load('activeContract');

        if (! $employee->activeContract) {
            throw new \InvalidArgumentException(
                'El empleado no tiene un contrato activo.'
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
                        'El empleado jornalero no tiene días trabajados registrados en este período.'
                    );
                }

                $baseSalary = round($employee->daily_rate * $workedDays, 2);
            } else {
                $baseSalary = $employee->base_salary;
            }

            $perceptions = $this->perceptionCalculator->calculate($employee, $period);
            $extras = $this->extraHourCalculator->calculate($employee, $period);
            $restDay = $this->restDayCalculator->calculate($employee, $period);
            $ipsBase = $baseSalary + $perceptions['ips_total'] + $extras['total'] + $restDay['total'];
            $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);
            $advances = $this->advanceCalculator->calculate($employee, $period);
            $merchandiseInstallments = $this->merchandiseInstallmentCalculator->calculate($employee, $period);
            $deductions = $this->deductionCalculator->calculate($employee, $period, $ipsBase);
            $absences = $this->absencePenaltyCalculator->calculate($employee, $period);
            $familyBonus = $this->familyBonusCalculator->calculate($employee, $period);
            $vacationPays = $this->resolveVacationPays($employee, $period);

            $totalPerceptions = $perceptions['total'] + $extras['total'] + $restDay['total'] + $familyBonus['total'] + $vacationPays['total'];
            $ipsPerceptions = $perceptions['ips_total'] + $extras['total'] + $restDay['total'];
            $totalDeductions = $deductions['total'] + $absences['total'];
            $netSalary = $baseSalary + $totalPerceptions - $totalDeductions;

            $payroll = Payroll::create([
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'base_salary' => $baseSalary,
                'total_perceptions' => $totalPerceptions,
                'ips_perceptions' => $ipsPerceptions,
                'total_deductions' => $totalDeductions,
                'gross_salary' => $baseSalary + $totalPerceptions,
                'net_salary' => $netSalary,
                'generated_at' => now(),
                'status' => 'draft',
                'payment_method' => $employee->activeContract?->payment_method === 'cash' ? 'cash' : 'transfer',
            ]);

            foreach (array_merge($perceptions['items'], $extras['items'], $restDay['items'], $familyBonus['items']) as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'perception_type' => $item['perception_type'] ?? null,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            foreach ($vacationPays['items'] as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            foreach (array_merge($deductions['items'], $absences['items']) as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'deduction',
                    'deduction_type' => $item['deduction_type'] ?? null,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            if ($loanInstallments['installments']->isNotEmpty()) {
                $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds, $payroll->id);
            }

            if ($advances['advances']->isNotEmpty()) {
                $advanceIds = $advances['advances']->pluck('id')->toArray();
                $this->advanceCalculator->markAdvancesAsPaid($advanceIds, $payroll->id);
            }

            if ($merchandiseInstallments['installments']->isNotEmpty()) {
                $merchandiseIds = $merchandiseInstallments['installments']->pluck('id')->toArray();
                $this->merchandiseInstallmentCalculator->markInstallmentsAsPaid($merchandiseIds, $payroll->id);
            }

            foreach ($vacationPays['vacations'] as $vacation) {
                VacationService::recordPayment($vacation, now());
            }

            $pdfPath = $this->payrollPDFGenerator->generate($payroll);
            $payroll->update(['pdf_path' => $pdfPath]);

            DB::commit();

            Log::info('Recibo de nómina generado manualmente', [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'period_id' => $period->id,
                'net_salary' => $netSalary,
            ]);

            return $payroll->refresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al generar recibo manualmente: '.$e->getMessage(), [
                'employee_id' => $employee->id,
                'period_id' => $period->id,
                'trace' => $e->getTraceAsString(),
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
            LoanInstallment::whereHas('loan', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', 'approved'))
                ->where('status', 'paid')
                ->whereBetween('due_date', [$period->start_date, $period->end_date])
                ->update(['status' => 'pending', 'paid_at' => null, 'payroll_id' => null]);

            // Restaurar outstanding_balance en los préstamos afectados
            LoanInstallment::whereHas('loan', fn ($q) => $q
                ->where('employee_id', $employee->id))
                ->whereBetween('due_date', [$period->start_date, $period->end_date])
                ->where('status', 'pending')
                ->with('loan')
                ->get()
                ->groupBy('loan_id')
                ->each(function ($installments) {
                    $loan = $installments->first()->loan;
                    if ($loan && $loan->status === 'approved') {
                        $pendingCapital = $loan->installments()
                            ->where('status', 'pending')
                            ->sum('capital_amount');
                        $loan->update(['outstanding_balance' => $pendingCapital]);
                    }
                });

            // Revertir cuotas de mercadería pagadas del período anterior
            MerchandiseWithdrawalInstallment::whereHas('withdrawal', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->where('status', 'approved'))
                ->where('status', 'paid')
                ->whereBetween('due_date', [$period->start_date, $period->end_date])
                ->update(['status' => 'pending', 'paid_at' => null, 'payroll_id' => null]);

            // Revertir adelantos asociados a esta nómina (caso normal)
            $advancesToRevert = Advance::where('employee_id', $employee->id)
                ->where('payroll_id', $payroll->id)
                ->get();

            if ($advancesToRevert->isNotEmpty()) {
                $byId = $advancesToRevert->pluck('employee_deduction_id')->filter();
                if ($byId->isNotEmpty()) {
                    EmployeeDeduction::whereIn('id', $byId)->delete();
                }

                Advance::where('employee_id', $employee->id)
                    ->where('payroll_id', $payroll->id)
                    ->update([
                        'status' => 'disbursed',
                        'payroll_id' => null,
                        'employee_deduction_id' => null,
                    ]);
            }

            // Limpieza de EmployeeDeductions huérfanas para adelantos disbursed sin nómina asignada.
            // Cubre el caso donde un intento de regeneración previo ya revirtió el payroll_id pero
            // no eliminó el registro EmployeeDeduction (el advance.employee_deduction_id ya es null).
            $ade001Id = Deduction::where('code', 'ADE001')->value('id');
            if ($ade001Id !== null) {
                $disbursedDates = Advance::where('employee_id', $employee->id)
                    ->where('status', 'disbursed')
                    ->whereNull('payroll_id')
                    ->pluck('approved_at')
                    ->filter()
                    ->map(fn ($d) => $d->toDateString())
                    ->unique();

                if ($disbursedDates->isNotEmpty()) {
                    EmployeeDeduction::where('employee_id', $employee->id)
                        ->where('deduction_id', $ade001Id)
                        ->whereIn('start_date', $disbursedDates)
                        ->delete();
                }
            }

            // Revertir remuneraciones vacacionales pagadas en esta nómina
            Vacation::where('employee_id', $employee->id)
                ->where('payment_method', 'with_payroll')
                ->where('payment_status', 'paid')
                ->whereBetween('start_date', [$period->start_date, $period->end_date])
                ->update(['payment_status' => 'unpaid', 'paid_at' => null]);

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

            // Recalcular con todos los calculadores — extras antes de deducciones para base IPS correcta
            $perceptions = $this->perceptionCalculator->calculate($employee, $period);
            $extras = $this->extraHourCalculator->calculate($employee, $period);
            $restDay = $this->restDayCalculator->calculate($employee, $period);
            $ipsBase = $baseSalary + $perceptions['ips_total'] + $extras['total'] + $restDay['total'];
            $loanInstallments = $this->loanInstallmentCalculator->calculate($employee, $period);
            $advances = $this->advanceCalculator->calculate($employee, $period);
            $merchandiseInstallments = $this->merchandiseInstallmentCalculator->calculate($employee, $period);
            $deductions = $this->deductionCalculator->calculate($employee, $period, $ipsBase);
            $absences = $this->absencePenaltyCalculator->calculate($employee, $period);
            $familyBonus = $this->familyBonusCalculator->calculate($employee, $period);
            $vacationPays = $this->resolveVacationPays($employee, $period);

            $totalPerceptions = $perceptions['total'] + $extras['total'] + $restDay['total'] + $familyBonus['total'] + $vacationPays['total'];
            $ipsPerceptions = $perceptions['ips_total'] + $extras['total'] + $restDay['total'];
            $totalDeductions = $deductions['total'] + $absences['total'];
            $netSalary = $baseSalary + $totalPerceptions - $totalDeductions;

            // Actualizar el registro existente
            $payroll->update([
                'base_salary' => $baseSalary,
                'total_perceptions' => $totalPerceptions,
                'ips_perceptions' => $ipsPerceptions,
                'total_deductions' => $totalDeductions,
                'gross_salary' => $baseSalary + $totalPerceptions,
                'net_salary' => $netSalary,
                'generated_at' => now(),
            ]);

            // Recrear ítems: percepciones (incluyendo descanso remunerado y bonificación familiar si aplican)
            foreach (array_merge($perceptions['items'], $extras['items'], $restDay['items'], $familyBonus['items']) as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'perception_type' => $item['perception_type'] ?? null,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            // Recrear ítems: remuneración vacacional with_payroll
            foreach ($vacationPays['items'] as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'perception',
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            // Recrear ítems: deducciones
            foreach (array_merge($deductions['items'], $absences['items']) as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'type' => 'deduction',
                    'deduction_type' => $item['deduction_type'] ?? null,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                ]);
            }

            // Marcar cuotas de préstamos como pagadas
            if ($loanInstallments['installments']->isNotEmpty()) {
                $installmentIds = $loanInstallments['installments']->pluck('id')->toArray();
                $this->loanInstallmentCalculator->markInstallmentsAsPaid($installmentIds, $payroll->id);
            }

            // Marcar adelantos como pagados
            if ($advances['advances']->isNotEmpty()) {
                $advanceIds = $advances['advances']->pluck('id')->toArray();
                $this->advanceCalculator->markAdvancesAsPaid($advanceIds, $payroll->id);
            }

            // Marcar cuotas de mercadería como pagadas
            if ($merchandiseInstallments['installments']->isNotEmpty()) {
                $merchandiseIds = $merchandiseInstallments['installments']->pluck('id')->toArray();
                $this->merchandiseInstallmentCalculator->markInstallmentsAsPaid($merchandiseIds, $payroll->id);
            }

            // Marcar remuneraciones vacacionales como pagadas
            foreach ($vacationPays['vacations'] as $vacation) {
                VacationService::recordPayment($vacation, now());
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
            Log::error('Error al regenerar recibo: '.$e->getMessage(), [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Busca vacaciones aprobadas con payment_method=with_payroll y payment_status=unpaid
     * cuyo start_date cae dentro del período. Retorna total, items y collection de vacaciones.
     *
     * La remuneración vacacional no suma a la base IPS (Art. 218 CLT).
     *
     * @return array{total: float, items: array, vacations: \Illuminate\Support\Collection}
     */
    private function resolveVacationPays(Employee $employee, PayrollPeriod $period): array
    {
        $vacations = Vacation::where('employee_id', $employee->id)
            ->where('payment_method', 'with_payroll')
            ->where('payment_status', 'unpaid')
            ->where('status', 'approved')
            ->whereBetween('start_date', [$period->start_date, $period->end_date])
            ->get();

        $total = 0.0;
        $items = [];

        foreach ($vacations as $vacation) {
            $amount = (float) ($vacation->payment_amount ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $total += $amount;
            $items[] = [
                'description' => 'Remuneración Vacacional ('
                    .$vacation->start_date->format('d/m')
                    .' – '
                    .$vacation->end_date->format('d/m/Y').')',
                'amount' => $amount,
            ];
        }

        return ['total' => $total, 'items' => $items, 'vacations' => $vacations];
    }
}
