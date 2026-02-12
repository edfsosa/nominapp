<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LiquidacionSeeder extends Seeder
{
    public function run(): void
    {
        // Solo empleados inactivos o suspendidos (desvinculados)
        $employees = Employee::whereIn('status', ['inactive', 'suspended'])->take(2)->get();
        $adminId = DB::table('users')->where('email', 'admin@example.com')->value('id');

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados inactivos/suspendidos para generar liquidaciones.');
            return;
        }

        DB::transaction(function () use ($employees, $adminId) {
            $now = now();
            $liquidaciones = [];
            $items = [];

            $terminationTypes = ['unjustified_dismissal', 'resignation'];

            foreach ($employees as $index => $employee) {
                $hireDate = Carbon::parse($employee->hire_date);
                $terminationDate = Carbon::now()->subDays(rand(10, 60));
                $baseSalary = $employee->base_salary ?? ($employee->daily_rate * 30);
                $dailySalary = round($baseSalary / 30);
                $terminationType = $terminationTypes[$index % count($terminationTypes)];

                $yearsOfService = $hireDate->diffInYears($terminationDate);
                $monthsOfService = $hireDate->diffInMonths($terminationDate) % 12;
                $daysOfService = (int) $hireDate->copy()->addYears($yearsOfService)->addMonths($monthsOfService)->diffInDays($terminationDate);

                // Promedio de salario últimos 6 meses (simulado)
                $avgSalary6m = round($baseSalary * (1 + rand(0, 10) / 100));

                // Preaviso: 30-90 días según antigüedad
                $preavisoDays = match (true) {
                    $yearsOfService >= 10 => 90,
                    $yearsOfService >= 5  => 60,
                    default               => 30,
                };
                $preavisoOtorgado = $terminationType === 'resignation';
                $preavisoAmount = $preavisoOtorgado ? 0 : round($dailySalary * $preavisoDays);

                // Indemnización: 15 días por año (despido injustificado)
                $indemnizacionAmount = $terminationType === 'unjustified_dismissal'
                    ? round($dailySalary * 15 * max(1, $yearsOfService))
                    : 0;

                // Vacaciones proporcionales
                $entitledDays = $yearsOfService >= 10 ? 30 : ($yearsOfService >= 5 ? 18 : 12);
                $vacacionesDays = round($entitledDays * $monthsOfService / 12);
                $vacacionesAmount = round($dailySalary * $vacacionesDays);

                // Aguinaldo proporcional
                $monthsInYear = $terminationDate->month;
                $aguinaldoAmount = round($baseSalary * $monthsInYear / 12);

                // Salario pendiente
                $salarioPendienteDays = $terminationDate->day;
                $salarioPendienteAmount = round($dailySalary * $salarioPendienteDays);

                // Deducciones
                $ipsDeduction = round(($salarioPendienteAmount + $preavisoAmount) * 0.09);
                $loanDeduction = 0;

                $totalHaberes = $preavisoAmount + $indemnizacionAmount + $vacacionesAmount + $aguinaldoAmount + $salarioPendienteAmount;
                $totalDeductions = $ipsDeduction + $loanDeduction;
                $netAmount = $totalHaberes - $totalDeductions;

                $liquidaciones[] = [
                    'employee_id'                   => $employee->id,
                    'termination_date'              => $terminationDate->toDateString(),
                    'termination_type'              => $terminationType,
                    'termination_reason'            => $terminationType === 'unjustified_dismissal'
                        ? 'Reestructuración organizacional'
                        : 'Renuncia voluntaria del trabajador',
                    'preaviso_otorgado'             => $preavisoOtorgado,
                    'hire_date'                     => $hireDate->toDateString(),
                    'base_salary'                   => $baseSalary,
                    'daily_salary'                  => $dailySalary,
                    'years_of_service'              => $yearsOfService,
                    'months_of_service'             => $monthsOfService,
                    'days_of_service'               => $daysOfService,
                    'average_salary_6m'             => $avgSalary6m,
                    'preaviso_days'                 => $preavisoOtorgado ? 0 : $preavisoDays,
                    'preaviso_amount'               => $preavisoAmount,
                    'indemnizacion_amount'          => $indemnizacionAmount,
                    'vacaciones_days'               => $vacacionesDays,
                    'vacaciones_amount'             => $vacacionesAmount,
                    'aguinaldo_proporcional_amount' => $aguinaldoAmount,
                    'salario_pendiente_days'        => $salarioPendienteDays,
                    'salario_pendiente_amount'      => $salarioPendienteAmount,
                    'ips_deduction'                 => $ipsDeduction,
                    'loan_deduction'                => $loanDeduction,
                    'other_deductions'              => 0,
                    'total_haberes'                 => $totalHaberes,
                    'total_deductions'              => $totalDeductions,
                    'net_amount'                    => $netAmount,
                    'status'                        => 'calculated',
                    'calculated_at'                 => $now,
                    'created_by_id'                 => $adminId,
                    'notes'                         => 'Liquidación generada por seeder de datos demo',
                    'created_at'                    => $now,
                    'updated_at'                    => $now,
                ];
            }

            DB::table('liquidaciones')->insert($liquidaciones);

            // Obtener IDs de liquidaciones insertadas
            $liquidacionIds = DB::table('liquidaciones')
                ->whereIn('employee_id', $employees->pluck('id'))
                ->orderBy('id')
                ->pluck('id', 'employee_id');

            foreach ($employees as $index => $employee) {
                $liqId = $liquidacionIds[$employee->id] ?? null;
                if (!$liqId) continue;

                $liq = $liquidaciones[$index];

                // Haberes
                if ($liq['preaviso_amount'] > 0) {
                    $items[] = [
                        'liquidacion_id' => $liqId,
                        'type'           => 'haber',
                        'category'       => 'preaviso',
                        'description'    => "Preaviso ({$liq['preaviso_days']} días)",
                        'amount'         => $liq['preaviso_amount'],
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }

                if ($liq['indemnizacion_amount'] > 0) {
                    $items[] = [
                        'liquidacion_id' => $liqId,
                        'type'           => 'haber',
                        'category'       => 'indemnizacion',
                        'description'    => "Indemnización ({$liq['years_of_service']} años)",
                        'amount'         => $liq['indemnizacion_amount'],
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }

                $items[] = [
                    'liquidacion_id' => $liqId,
                    'type'           => 'haber',
                    'category'       => 'vacaciones',
                    'description'    => "Vacaciones proporcionales ({$liq['vacaciones_days']} días)",
                    'amount'         => $liq['vacaciones_amount'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                $items[] = [
                    'liquidacion_id' => $liqId,
                    'type'           => 'haber',
                    'category'       => 'aguinaldo',
                    'description'    => 'Aguinaldo proporcional',
                    'amount'         => $liq['aguinaldo_proporcional_amount'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                $items[] = [
                    'liquidacion_id' => $liqId,
                    'type'           => 'haber',
                    'category'       => 'salario_pendiente',
                    'description'    => "Salario pendiente ({$liq['salario_pendiente_days']} días)",
                    'amount'         => $liq['salario_pendiente_amount'],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                // Deducciones
                if ($liq['ips_deduction'] > 0) {
                    $items[] = [
                        'liquidacion_id' => $liqId,
                        'type'           => 'deduction',
                        'category'       => 'ips',
                        'description'    => 'Aporte IPS (9%)',
                        'amount'         => $liq['ips_deduction'],
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            if ($items) {
                DB::table('liquidacion_items')->insert($items);
            }
        });
    }
}
