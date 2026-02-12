<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AguinaldoSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DB::table('companies')->value('id');

        if (!$companyId) {
            $this->command->warn('Se necesita una empresa. Ejecuta CompanySeeder primero.');
            return;
        }

        $employees = Employee::all();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados para generar aguinaldos.');
            return;
        }

        DB::transaction(function () use ($companyId, $employees) {
            $now = now();
            $previousYear = (int) date('Y') - 1;

            // Crear período de aguinaldo del año anterior (cerrado)
            $periodId = DB::table('aguinaldo_periods')->insertGetId([
                'company_id' => $companyId,
                'year'       => $previousYear,
                'status'     => 'closed',
                'closed_at'  => Carbon::create($previousYear, 12, 20)->toDateTimeString(),
                'notes'      => "Aguinaldo correspondiente al ejercicio $previousYear",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $aguinaldoRows = [];
            $itemRows = [];

            foreach ($employees as $employee) {
                $hireDate = Carbon::parse($employee->hire_date);
                $baseSalary = $employee->base_salary ?? ($employee->daily_rate * 30);

                // Calcular meses trabajados en el año anterior
                $yearStart = Carbon::create($previousYear, 1, 1);
                $yearEnd = Carbon::create($previousYear, 12, 31);
                $effectiveStart = $hireDate->gt($yearStart) ? $hireDate : $yearStart;
                $monthsWorked = min(12, $effectiveStart->diffInMonths($yearEnd) + 1);

                $totalEarned = $baseSalary * $monthsWorked;
                $aguinaldoAmount = round($totalEarned / 12);

                $aguinaldoRows[] = [
                    'aguinaldo_period_id' => $periodId,
                    'employee_id'         => $employee->id,
                    'total_earned'        => $totalEarned,
                    'months_worked'       => $monthsWorked,
                    'aguinaldo_amount'    => $aguinaldoAmount,
                    'generated_at'        => Carbon::create($previousYear, 12, 20)->toDateTimeString(),
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }

            DB::table('aguinaldos')->insert($aguinaldoRows);

            // Obtener IDs de aguinaldos insertados
            $aguinaldoMap = DB::table('aguinaldos')
                ->where('aguinaldo_period_id', $periodId)
                ->pluck('id', 'employee_id');

            $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

            foreach ($employees as $employee) {
                $aguinaldoId = $aguinaldoMap[$employee->id] ?? null;
                if (!$aguinaldoId) continue;

                $baseSalary = $employee->base_salary ?? ($employee->daily_rate * 30);
                $hireDate = Carbon::parse($employee->hire_date);
                $yearStart = Carbon::create($previousYear, 1, 1);
                $effectiveStart = $hireDate->gt($yearStart) ? $hireDate : $yearStart;
                $startMonth = $effectiveStart->month;

                // Generar items mensuales
                for ($m = $startMonth; $m <= 12; $m++) {
                    $perceptions = round($baseSalary * rand(0, 10) / 100);
                    $extraHours = round($baseSalary * rand(0, 5) / 100);

                    $itemRows[] = [
                        'aguinaldo_id' => $aguinaldoId,
                        'month'        => $months[$m - 1] . " $previousYear",
                        'base_salary'  => $baseSalary,
                        'perceptions'  => $perceptions,
                        'extra_hours'  => $extraHours,
                        'total'        => $baseSalary + $perceptions + $extraHours,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }
            }

            if ($itemRows) {
                foreach (array_chunk($itemRows, 500) as $chunk) {
                    DB::table('aguinaldo_items')->insert($chunk);
                }
            }
        });
    }
}
