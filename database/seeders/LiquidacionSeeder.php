<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra liquidaciones de ejemplo para empleados inactivos/suspendidos.
 *
 * Usa los datos reales del último contrato del empleado (salario, fecha de ingreso,
 * tipo de salario) en lugar de valores aleatorios.
 * Calcula haberes según el Código Laboral Paraguayo (Ley 213/93).
 */
class LiquidacionSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::whereIn('status', ['inactive', 'suspended'])
            ->with(['contracts' => fn($q) => $q->orderByDesc('start_date')])
            ->get();

        $adminId = DB::table('users')->value('id');

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados inactivos/suspendidos para generar liquidaciones.');
            return;
        }

        DB::transaction(function () use ($employees, $adminId) {
            $now              = now();
            $liquidaciones    = [];
            $items            = [];
            $terminationTypes = ['unjustified_dismissal', 'resignation'];

            foreach ($employees as $index => $employee) {
                // Usar datos reales del último contrato
                $contract        = $employee->contracts->first();
                $baseSalary      = $contract ? (int) $contract->salary : 3000000;
                $salaryType      = $contract?->salary_type ?? 'mensual';
                $hireDate        = Carbon::parse($contract?->start_date ?? now()->subYears(3));
                $terminationDate = Carbon::now()->subDays(15 + ($index * 20));
                $terminationType = $terminationTypes[$index % count($terminationTypes)];

                $dailySalary     = $salaryType === 'jornal' ? $baseSalary : (int) round($baseSalary / 30);
                $yearsOfService  = $hireDate->diffInYears($terminationDate);
                $monthsOfService = $hireDate->diffInMonths($terminationDate) % 12;
                $daysOfService   = (int) $hireDate->copy()
                    ->addYears($yearsOfService)
                    ->addMonths($monthsOfService)
                    ->diffInDays($terminationDate);

                // Promedio salarial últimos 6 meses (5% sobre base para demo)
                $avgSalary6m = (int) round($baseSalary * 1.05);

                // Preaviso (Art. 87 CLT): 30/60/90 días según antigüedad
                $preavisoDays    = match (true) {
                    $yearsOfService >= 10 => 90,
                    $yearsOfService >= 5  => 60,
                    default               => 30,
                };
                $preavisoOtorgado = ($terminationType === 'resignation');
                $preavisoAmount   = $preavisoOtorgado ? 0 : (int) round($dailySalary * $preavisoDays);

                // Indemnización (Art. 92 CLT): 15 días/año en despido injustificado
                $indemnizacionAmount = $terminationType === 'unjustified_dismissal'
                    ? (int) round($dailySalary * 15 * max(1, $yearsOfService))
                    : 0;

                // Indemnización por estabilidad (Art. 96 CLT): aplica con 10+ años de servicio
                $indemnizacionEstabilidadAmount = ($yearsOfService >= 10 && $terminationType === 'unjustified_dismissal')
                    ? (int) round($dailySalary * 15 * $yearsOfService)
                    : 0;

                // Vacaciones proporcionales: días según tramo de antigüedad, proporcional a meses
                $entitledDays    = match (true) {
                    $yearsOfService >= 10 => 30,
                    $yearsOfService >= 5  => 18,
                    default               => 12,
                };
                $vacacionesDays   = (int) round($entitledDays * ($terminationDate->month) / 12);
                $vacacionesAmount = (int) round($dailySalary * $vacacionesDays);

                // Aguinaldo proporcional: salario × meses transcurridos en el año / 12
                $aguinaldoAmount = (int) round($baseSalary * $terminationDate->month / 12);

                // Salario pendiente: días trabajados en el mes de desvinculación
                $salarioPendienteDays   = $terminationDate->day;
                $salarioPendienteAmount = (int) round($dailySalary * $salarioPendienteDays);

                // Deducciones
                $ipsBase      = $salarioPendienteAmount + $preavisoAmount;
                $ipsDeduction = (int) round($ipsBase * 0.09);

                $totalHaberes     = $preavisoAmount + $indemnizacionAmount + $indemnizacionEstabilidadAmount
                    + $vacacionesAmount + $aguinaldoAmount + $salarioPendienteAmount;
                $totalDeductions  = $ipsDeduction;
                $netAmount        = $totalHaberes - $totalDeductions;

                $liquidaciones[] = [
                    'employee_id'                        => $employee->id,
                    'termination_date'                   => $terminationDate->toDateString(),
                    'termination_type'                   => $terminationType,
                    'termination_reason'                 => $terminationType === 'unjustified_dismissal'
                        ? 'Reestructuración organizacional'
                        : 'Renuncia voluntaria del trabajador',
                    'preaviso_otorgado'                  => $preavisoOtorgado,
                    'hire_date'                          => $hireDate->toDateString(),
                    'base_salary'                        => $baseSalary,
                    'daily_salary'                       => $dailySalary,
                    'salary_type'                        => $salaryType,
                    'years_of_service'                   => $yearsOfService,
                    'months_of_service'                  => $monthsOfService,
                    'days_of_service'                    => $daysOfService,
                    'average_salary_6m'                  => $avgSalary6m,
                    'preaviso_days'                      => $preavisoOtorgado ? 0 : $preavisoDays,
                    'preaviso_amount'                    => $preavisoAmount,
                    'indemnizacion_amount'               => $indemnizacionAmount,
                    'indemnizacion_estabilidad_amount'   => $indemnizacionEstabilidadAmount,
                    'vacaciones_days'                    => $vacacionesDays,
                    'vacaciones_amount'                  => $vacacionesAmount,
                    'aguinaldo_proporcional_amount'      => $aguinaldoAmount,
                    'salario_pendiente_days'             => $salarioPendienteDays,
                    'salario_pendiente_amount'           => $salarioPendienteAmount,
                    'ips_deduction'                      => $ipsDeduction,
                    'loan_deduction'                     => 0,
                    'other_deductions'                   => 0,
                    'total_haberes'                      => $totalHaberes,
                    'total_deductions'                   => $totalDeductions,
                    'net_amount'                         => $netAmount,
                    'status'                             => 'calculated',
                    'calculated_at'                      => $now,
                    'created_by_id'                      => $adminId,
                    'notes'                              => 'Liquidación generada por seeder de datos demo',
                    'created_at'                         => $now,
                    'updated_at'                         => $now,
                ];
            }

            DB::table('liquidaciones')->insert($liquidaciones);

            $liquidacionIds = DB::table('liquidaciones')
                ->whereIn('employee_id', $employees->pluck('id'))
                ->orderBy('id')
                ->pluck('id', 'employee_id');

            foreach ($employees as $index => $employee) {
                $liqId = $liquidacionIds[$employee->id] ?? null;
                if (! $liqId) continue;

                $liq = $liquidaciones[$index];

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
                        'description'    => "Indemnización ({$liq['years_of_service']} año(s) × 15 días)",
                        'amount'         => $liq['indemnizacion_amount'],
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }

                if ($liq['indemnizacion_estabilidad_amount'] > 0) {
                    $items[] = [
                        'liquidacion_id' => $liqId,
                        'type'           => 'haber',
                        'category'       => 'indemnizacion_estabilidad',
                        'description'    => 'Indemnización por estabilidad laboral (Art. 96 CLT)',
                        'amount'         => $liq['indemnizacion_estabilidad_amount'],
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

                $termMonth = Carbon::parse($liq['termination_date'])->month;
                $items[] = [
                    'liquidacion_id' => $liqId,
                    'type'           => 'haber',
                    'category'       => 'aguinaldo',
                    'description'    => "Aguinaldo proporcional ($termMonth meses)",
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
