<?php

namespace App\Services;

use App\Models\Aguinaldo;
use App\Models\AguinaldoItem;
use App\Models\AguinaldoPeriod;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AguinaldoService
{
    protected AguinaldoPDFGenerator $pdfGenerator;

    public function __construct(AguinaldoPDFGenerator $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
    }

    public function generateForPeriod(AguinaldoPeriod $period): int
    {
        $count = 0;
        $year = $period->year;
        $companyId = $period->company_id;

        // Obtener empleados activos de la empresa (a través de sucursal)
        $employees = Employee::query()
            ->whereHas('branch', fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['active', 'suspended'])
            ->get();

        foreach ($employees as $employee) {
            // Evitar duplicados
            if (Aguinaldo::where('aguinaldo_period_id', $period->id)
                ->where('employee_id', $employee->id)
                ->exists()
            ) {
                continue;
            }

            // Obtener payrolls del empleado en el año
            $payrolls = Payroll::with(['period', 'items'])
                ->where('employee_id', $employee->id)
                ->whereHas('period', function ($query) use ($year) {
                    $query->whereYear('start_date', $year);
                })
                ->orderBy('created_at')
                ->get();

            // Si no tiene payrolls en el año, saltar
            if ($payrolls->isEmpty()) {
                continue;
            }

            DB::beginTransaction();

            try {
                $totalEarned = 0;
                $items = [];

                foreach ($payrolls as $payroll) {
                    // Obtener horas extras del desglose (si existe)
                    $extraHoursAmount = $payroll->items
                        ->where('type', 'perception')
                        ->filter(fn($item) => str_contains(strtolower($item->description), 'hora'))
                        ->sum('amount');

                    // Percepciones sin horas extras
                    $perceptionsWithoutExtras = $payroll->total_perceptions - $extraHoursAmount;

                    // Total del mes (base + percepciones que ya incluye horas extras)
                    $monthTotal = $payroll->base_salary + $payroll->total_perceptions;
                    $totalEarned += $monthTotal;

                    // Obtener nombre del mes en español
                    $monthName = $payroll->period->start_date->translatedFormat('F');

                    $items[] = [
                        'month' => ucfirst($monthName),
                        'base_salary' => $payroll->base_salary,
                        'perceptions' => $perceptionsWithoutExtras,
                        'extra_hours' => $extraHoursAmount,
                        'total' => $monthTotal,
                    ];
                }

                // Calcular meses trabajados y aguinaldo
                $monthsWorked = count($payrolls);
                $aguinaldoAmount = round($totalEarned / 12, 2);

                // Crear aguinaldo
                $aguinaldo = Aguinaldo::create([
                    'aguinaldo_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'total_earned' => $totalEarned,
                    'months_worked' => $monthsWorked,
                    'aguinaldo_amount' => $aguinaldoAmount,
                    'generated_at' => now(),
                ]);

                // Crear items (desglose mensual)
                foreach ($items as $item) {
                    AguinaldoItem::create([
                        'aguinaldo_id' => $aguinaldo->id,
                        'month' => $item['month'],
                        'base_salary' => $item['base_salary'],
                        'perceptions' => $item['perceptions'],
                        'extra_hours' => $item['extra_hours'],
                        'total' => $item['total'],
                    ]);
                }

                // Generar PDF
                $pdfPath = $this->pdfGenerator->generate($aguinaldo);
                $aguinaldo->update(['pdf_path' => $pdfPath]);

                DB::commit();
                $count++;
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Error al generar aguinaldo: ' . $e->getMessage(), [
                    'employee_id' => $employee->id,
                    'period_id' => $period->id,
                ]);
            }
        }

        return $count;
    }
}
