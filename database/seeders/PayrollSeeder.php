<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra nóminas de ejemplo para períodos cerrados.
 *
 * Genera nóminas para:
 *   - Hasta 3 períodos mensuales cerrados → empleados con payroll_type = 'monthly'
 *   - Hasta 4 períodos semanales cerrados → empleados con payroll_type = 'weekly' (jornaleros)
 *
 * Cálculo simplificado (demo, no usa PayrollService):
 *   gross_salary    = base_salary + total_perceptions
 *   IPS             = gross_salary × 9 %
 *   total_deductions = IPS + otras deducciones activas
 *   net_salary      = gross_salary − total_deductions
 *
 * Estados:
 *   - Período más reciente cerrado → 'approved' (con approved_by_id / approved_at)
 *   - Períodos anteriores          → 'paid'
 *
 * Items por nómina:
 *   - perception  "Salario base"                → base_salary
 *   - perception  <nombre percepción activa>    → monto de cada percepción activa
 *   - deduction   "Aporte IPS (9%)"             → 9% del gross
 *   - deduction   <nombre deducción adicional>  → si el empleado tiene otras deducciones
 *
 * Depende de: PayrollPeriodSeeder, EmployeeSeeder, EmployeePerceptionSeeder, DeductionSeeder.
 */
class PayrollSeeder extends Seeder
{
    /** Máximo de períodos mensuales cerrados a poblar. */
    private const MAX_MONTHLY = 3;

    /** Máximo de períodos semanales cerrados a poblar. */
    private const MAX_WEEKLY = 4;

    public function run(): void
    {
        $userId = DB::table('users')->value('id');
        $now    = now();

        // ─── Catálogos ────────────────────────────────────────────────────
        $perceptionCatalog = DB::table('perceptions')
            ->where('is_active', true)
            ->get(['id', 'name', 'calculation', 'amount', 'percent'])
            ->keyBy('id');

        $deductionCatalog = DB::table('deductions')
            ->where('is_active', true)
            ->get(['id', 'name', 'code', 'calculation', 'amount', 'percent'])
            ->keyBy('id');

        $ipsDeduction = $deductionCatalog->first(fn($d) => $d->code === 'IPS001');
        $ipsRate      = $ipsDeduction ? (float) $ipsDeduction->percent / 100 : 0.09;

        // ─── Empleados con contratos activos ─────────────────────────────
        $employees = DB::table('employees as e')
            ->join('contracts as c', function ($j) {
                $j->on('c.employee_id', '=', 'e.id')
                  ->where('c.status', 'active');
            })
            ->where('e.status', 'active')
            ->get([
                'e.id as employee_id',
                'c.salary',
                'c.salary_type',
                'c.payroll_type',
                'c.id as contract_id',
            ]);

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos con contrato. Ejecuta EmployeeSeeder primero.');
            return;
        }

        // ─── Percepciones activas por empleado ────────────────────────────
        $empPerceptions = DB::table('employee_perceptions')
            ->whereNull('end_date')
            ->get(['employee_id', 'perception_id', 'custom_amount']);

        $percByEmployee = [];
        foreach ($empPerceptions as $ep) {
            $percByEmployee[$ep->employee_id][] = $ep;
        }

        // ─── Deducciones activas no-IPS por empleado ─────────────────────
        $empDeductions = DB::table('employee_deductions as ed')
            ->join('deductions as d', 'd.id', '=', 'ed.deduction_id')
            ->whereNull('ed.end_date')
            ->where('d.code', '!=', 'IPS001')
            ->get(['ed.employee_id', 'ed.deduction_id', 'ed.custom_amount']);

        $dedByEmployee = [];
        foreach ($empDeductions as $ed) {
            $dedByEmployee[$ed->employee_id][] = $ed;
        }

        // ─── Períodos mensuales cerrados (más recientes primero) ──────────
        $monthlyPeriods = DB::table('payroll_periods')
            ->where('frequency', 'monthly')
            ->where('status', 'closed')
            ->orderByDesc('end_date')
            ->limit(self::MAX_MONTHLY)
            ->get()
            ->values();

        // ─── Períodos semanales cerrados (más recientes primero) ──────────
        $weeklyPeriods = DB::table('payroll_periods')
            ->where('frequency', 'weekly')
            ->where('status', 'closed')
            ->orderByDesc('end_date')
            ->limit(self::MAX_WEEKLY)
            ->get()
            ->values();

        if ($monthlyPeriods->isEmpty() && $weeklyPeriods->isEmpty()) {
            $this->command->warn('No hay períodos cerrados. Ejecuta PayrollPeriodSeeder primero.');
            return;
        }

        $payrollRows = [];
        $itemRows    = [];

        // ─── Generar nóminas mensuales ────────────────────────────────────
        $monthlyEmployees = $employees->where('payroll_type', 'monthly');

        foreach ($monthlyPeriods as $periodIndex => $period) {
            $isMostRecent = $periodIndex === 0;

            foreach ($monthlyEmployees as $employee) {
                [$payrollRow, $items] = $this->buildPayroll(
                    employee:       $employee,
                    periodId:       $period->id,
                    isMostRecent:   $isMostRecent,
                    ipsRate:        $ipsRate,
                    perceptions:    $percByEmployee[$employee->employee_id] ?? [],
                    deductions:     $dedByEmployee[$employee->employee_id] ?? [],
                    perceptionCatalog: $perceptionCatalog,
                    deductionCatalog:  $deductionCatalog,
                    userId:         $userId,
                    now:            $now,
                    periodEnd:      $period->end_date,
                );

                $payrollRows[] = $payrollRow;
                $itemRows[]    = $items;
            }
        }

        // ─── Generar nóminas semanales ────────────────────────────────────
        $weeklyEmployees = $employees->where('payroll_type', 'weekly');

        foreach ($weeklyPeriods as $periodIndex => $period) {
            $isMostRecent = $periodIndex === 0;

            foreach ($weeklyEmployees as $employee) {
                [$payrollRow, $items] = $this->buildPayroll(
                    employee:       $employee,
                    periodId:       $period->id,
                    isMostRecent:   $isMostRecent,
                    ipsRate:        $ipsRate,
                    perceptions:    $percByEmployee[$employee->employee_id] ?? [],
                    deductions:     $dedByEmployee[$employee->employee_id] ?? [],
                    perceptionCatalog: $perceptionCatalog,
                    deductionCatalog:  $deductionCatalog,
                    userId:         $userId,
                    now:            $now,
                    periodEnd:      $period->end_date,
                    isWeekly:       true,
                );

                $payrollRows[] = $payrollRow;
                $itemRows[]    = $items;
            }
        }

        if (empty($payrollRows)) {
            $this->command->warn('No se generaron nóminas (sin empleados o períodos cerrados).');
            return;
        }

        DB::transaction(function () use ($payrollRows, $itemRows, $now) {
            // Insertar nóminas en chunks
            foreach (array_chunk($payrollRows, 500) as $chunk) {
                DB::table('payrolls')->insert($chunk);
            }

            // Recuperar IDs por employee_id + payroll_period_id
            $allPeriodIds   = array_unique(array_column($payrollRows, 'payroll_period_id'));
            $allEmployeeIds = array_unique(array_column($payrollRows, 'employee_id'));

            $payrollIds = DB::table('payrolls')
                ->whereIn('payroll_period_id', $allPeriodIds)
                ->whereIn('employee_id', $allEmployeeIds)
                ->get(['id', 'employee_id', 'payroll_period_id'])
                ->groupBy(fn($r) => "{$r->payroll_period_id}:{$r->employee_id}")
                ->map(fn($g) => $g->first()->id);

            // Construir filas de items con el ID de nómina resuelto
            $flatItems = [];
            foreach ($itemRows as $i => $items) {
                $row      = $payrollRows[$i];
                $key      = "{$row['payroll_period_id']}:{$row['employee_id']}";
                $payrollId = $payrollIds[$key] ?? null;

                if (! $payrollId) continue;

                foreach ($items as $item) {
                    $flatItems[] = array_merge($item, [
                        'payroll_id' => $payrollId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            foreach (array_chunk($flatItems, 500) as $chunk) {
                DB::table('payroll_items')->insert($chunk);
            }
        });

        $payrollCount = count($payrollRows);
        $itemCount    = array_sum(array_map('count', $itemRows));
        $this->command->info("Nóminas sembradas: $payrollCount nóminas, $itemCount ítems.");
    }

    /**
     * Construye una fila de nómina y sus ítems para un empleado y período dados.
     *
     * @param  object                        $employee           Fila de BD con salary, salary_type, payroll_type
     * @param  int                           $periodId
     * @param  bool                          $isMostRecent       true → status 'approved'; false → 'paid'
     * @param  float                         $ipsRate            Tasa IPS (0.09)
     * @param  array<int, object>            $perceptions        employee_perceptions activas del empleado
     * @param  array<int, object>            $deductions         employee_deductions activas no-IPS
     * @param  \Illuminate\Support\Collection $perceptionCatalog
     * @param  \Illuminate\Support\Collection $deductionCatalog
     * @param  int|null                      $userId
     * @param  \Carbon\Carbon               $now
     * @param  string                        $periodEnd          Fecha fin del período (para generated_at)
     * @param  bool                          $isWeekly           true → dividir salario mensual entre 4.33
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function buildPayroll(
        object $employee,
        int $periodId,
        bool $isMostRecent,
        float $ipsRate,
        array $perceptions,
        array $deductions,
        $perceptionCatalog,
        $deductionCatalog,
        ?int $userId,
        Carbon $now,
        string $periodEnd,
        bool $isWeekly = false,
    ): array {
        // Base salary: jornaleros semanales usan 5 días × daily_rate
        $monthlySalary = (int) $employee->salary;

        $baseSalary = $isWeekly
            ? (int) round($monthlySalary * 5)           // 5 días × jornal
            : $monthlySalary;

        // ─── Percepciones ────────────────────────────────────────────────
        $totalPerceptions = 0;
        $perceptionItems  = [];

        foreach ($perceptions as $ep) {
            $catalog = $perceptionCatalog[$ep->perception_id] ?? null;
            if (! $catalog) continue;

            $amount = $ep->custom_amount
                ? (int) $ep->custom_amount
                : ($catalog->calculation === 'percentage'
                    ? (int) round($baseSalary * (float) $catalog->percent / 100)
                    : (int) $catalog->amount);

            $totalPerceptions += $amount;

            $perceptionItems[] = [
                'type'        => 'perception',
                'description' => $catalog->name,
                'amount'      => $amount,
            ];
        }

        $grossSalary = $baseSalary + $totalPerceptions;

        // ─── Deducciones ─────────────────────────────────────────────────
        $ipsAmount       = (int) round($grossSalary * $ipsRate);
        $totalDeductions = $ipsAmount;
        $deductionItems  = [
            [
                'type'        => 'deduction',
                'description' => 'Aporte IPS (9%)',
                'amount'      => $ipsAmount,
            ],
        ];

        foreach ($deductions as $ed) {
            $catalog = $deductionCatalog[$ed->deduction_id] ?? null;
            if (! $catalog) continue;

            $amount = $ed->custom_amount
                ? (int) $ed->custom_amount
                : ($catalog->calculation === 'percentage'
                    ? (int) round($grossSalary * (float) $catalog->percent / 100)
                    : (int) $catalog->amount);

            $totalDeductions += $amount;

            $deductionItems[] = [
                'type'        => 'deduction',
                'description' => $catalog->name,
                'amount'      => $amount,
            ];
        }

        $netSalary = $grossSalary - $totalDeductions;

        // ─── Estado y aprobación ──────────────────────────────────────────
        $status     = $isMostRecent ? 'approved' : 'paid';
        $approvedAt = Carbon::parse($periodEnd)->addDays(3)->toDateTimeString();

        $payrollRow = [
            'employee_id'       => $employee->employee_id,
            'payroll_period_id' => $periodId,
            'base_salary'       => $baseSalary,
            'gross_salary'      => $grossSalary,
            'total_perceptions' => $totalPerceptions,
            'total_deductions'  => $totalDeductions,
            'net_salary'        => $netSalary,
            'pdf_path'          => null,
            'generated_at'      => $approvedAt,
            'status'            => $status,
            'approved_by_id'    => $userId,
            'approved_at'       => $approvedAt,
            'deleted_at'        => null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        // Items: salario base primero, luego percepciones, luego deducciones
        $items = array_merge(
            [['type' => 'perception', 'description' => 'Salario base', 'amount' => $baseSalary]],
            $perceptionItems,
            $deductionItems,
        );

        return [$payrollRow, $items];
    }
}
