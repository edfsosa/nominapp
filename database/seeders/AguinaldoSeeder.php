<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el período de aguinaldo del año anterior (cerrado y pagado)
 * y un período del año en curso (en procesamiento) para todos los
 * empleados activos.
 *
 * El aguinaldo equivale a la doceava parte de la remuneración total
 * percibida en el año (Art. 243 Código Laboral — Ley 213/93 Paraguay).
 *
 * Valores deterministas: las percepciones y horas extras por mes se
 * calculan con módulo sobre employee_id + mes para garantizar
 * reproducibilidad entre ejecuciones.
 */
class AguinaldoSeeder extends Seeder
{
    /** Porcentaje máximo de percepciones sobre salario base (factor 100). */
    private const PERCEPTION_PCT = 8;

    /** Porcentaje máximo de horas extras sobre salario base (factor 100). */
    private const EXTRA_HOURS_PCT = 4;

    public function run(): void
    {
        $companyId = DB::table('companies')->value('id');

        if (! $companyId) {
            $this->command->warn('Se necesita una empresa. Ejecuta CompanySeeder primero.');
            return;
        }

        $employees = Employee::where('status', 'active')
            ->with('activeContract')
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar aguinaldos.');
            return;
        }

        DB::transaction(function () use ($companyId, $employees) {
            $now         = now();
            $currentYear = (int) date('Y');
            $prevYear    = $currentYear - 1;

            // ─── Período año anterior (cerrado y pagado) ──────────────────────
            $prevPeriodId = DB::table('aguinaldo_periods')->insertGetId([
                'company_id' => $companyId,
                'year'       => $prevYear,
                'status'     => 'closed',
                'closed_at'  => Carbon::create($prevYear, 12, 20)->toDateTimeString(),
                'notes'      => "Aguinaldo correspondiente al ejercicio $prevYear",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // ─── Período año en curso (en procesamiento) ──────────────────────
            $currPeriodId = DB::table('aguinaldo_periods')->insertGetId([
                'company_id' => $companyId,
                'year'       => $currentYear,
                'status'     => 'processing',
                'closed_at'  => null,
                'notes'      => "Aguinaldo en curso — ejercicio $currentYear",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $aguinaldoRows = [];
            $itemRows      = [];

            foreach ($employees as $employee) {
                $contract   = $employee->activeContract;
                $baseSalary = $contract
                    ? (int) ($contract->salary_type === 'jornal'
                        ? $contract->salary * 30
                        : $contract->salary)
                    : 2850240; // SMM vigente como fallback

                $hireDate = $contract
                    ? Carbon::parse($contract->start_date)
                    : Carbon::now()->subYear();

                // ─── Año anterior ─────────────────────────────────────────────
                $prevYearStart   = Carbon::create($prevYear, 1, 1);
                $prevYearEnd     = Carbon::create($prevYear, 12, 31);
                $effectiveStart  = $hireDate->gt($prevYearStart) ? $hireDate->copy() : $prevYearStart->copy();
                $startMonth      = $effectiveStart->month;
                $monthsWorked    = 12 - $startMonth + 1;

                [$prevTotalEarned, $prevItems] = $this->buildItems(
                    $employee->id,
                    $baseSalary,
                    $prevYear,
                    $startMonth,
                    12
                );

                $prevAguinaldoAmount = (int) round($prevTotalEarned / 12);

                $aguinaldoRows[] = [
                    'period_id'    => $prevPeriodId,
                    'is_prev_year' => true,
                    'employee_id'  => $employee->id,
                    'total_earned' => $prevTotalEarned,
                    'months_worked'=> $monthsWorked,
                    'aguinaldo_amount' => $prevAguinaldoAmount,
                    'status'       => 'paid',
                    'paid_at'      => Carbon::create($prevYear, 12, 20)->toDateTimeString(),
                    'generated_at' => Carbon::create($prevYear, 12, 20)->toDateTimeString(),
                    'pdf_path'     => null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                    '_items'       => $prevItems,
                ];

                // ─── Año en curso (meses transcurridos) ───────────────────────
                $currYearStart  = Carbon::create($currentYear, 1, 1);
                $effectiveStart2 = $hireDate->gt($currYearStart) ? $hireDate->copy() : $currYearStart->copy();
                $startMonthCurr = $effectiveStart2->month;
                $currentMonth   = (int) date('n');

                if ($startMonthCurr <= $currentMonth) {
                    [$currTotalEarned, $currItems] = $this->buildItems(
                        $employee->id,
                        $baseSalary,
                        $currentYear,
                        $startMonthCurr,
                        $currentMonth
                    );

                    $monthsWorkedCurr    = $currentMonth - $startMonthCurr + 1;
                    $currAguinaldoAmount = (int) round($currTotalEarned / 12);

                    $aguinaldoRows[] = [
                        'period_id'    => $currPeriodId,
                        'is_prev_year' => false,
                        'employee_id'  => $employee->id,
                        'total_earned' => $currTotalEarned,
                        'months_worked'=> $monthsWorkedCurr,
                        'aguinaldo_amount' => $currAguinaldoAmount,
                        'status'       => 'pending',
                        'paid_at'      => null,
                        'generated_at' => $now->toDateTimeString(),
                        'pdf_path'     => null,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                        '_items'       => $currItems,
                    ];
                }
            }

            // Insertar aguinaldos (sin la clave auxiliar _items)
            $toInsert = array_map(function ($row) {
                $periodId = $row['period_id'];
                unset($row['period_id'], $row['is_prev_year'], $row['_items']);
                $row['aguinaldo_period_id'] = $periodId;
                return $row;
            }, $aguinaldoRows);

            DB::table('aguinaldos')->insert($toInsert);

            // Recuperar IDs por period_id + employee_id
            $aguinaldoIds = DB::table('aguinaldos')
                ->whereIn('aguinaldo_period_id', [$prevPeriodId, $currPeriodId])
                ->get(['id', 'aguinaldo_period_id', 'employee_id'])
                ->groupBy(fn($r) => "{$r->aguinaldo_period_id}:{$r->employee_id}")
                ->map(fn($g) => $g->first()->id);

            foreach ($aguinaldoRows as $row) {
                $key          = "{$row['period_id']}:{$row['employee_id']}";
                $aguinaldoId  = $aguinaldoIds[$key] ?? null;
                if (! $aguinaldoId) continue;

                foreach ($row['_items'] as $item) {
                    $itemRows[] = array_merge($item, [
                        'aguinaldo_id' => $aguinaldoId,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }

            foreach (array_chunk($itemRows, 500) as $chunk) {
                DB::table('aguinaldo_items')->insert($chunk);
            }
        });
    }

    /**
     * Genera los ítems mensuales para un aguinaldo y retorna el total acumulado.
     *
     * Las percepciones y horas extras son deterministas (módulo sobre
     * employee_id × mes) para garantizar reproducibilidad.
     *
     * @param  int   $employeeId  ID del empleado (fuente de variación)
     * @param  int   $baseSalary  Salario base mensual en guaraníes
     * @param  int   $year        Año del período
     * @param  int   $fromMonth   Mes inicial (1–12)
     * @param  int   $toMonth     Mes final   (1–12)
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    private function buildItems(int $employeeId, int $baseSalary, int $year, int $fromMonth, int $toMonth): array
    {
        $spanishMonths = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',    4 => 'Abril',
            5 => 'Mayo',  6 => 'Junio',   7 => 'Julio',     8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $items      = [];
        $totalEarned = 0;

        for ($m = $fromMonth; $m <= $toMonth; $m++) {
            // Determinista: varía por empleado y mes, sin rand()
            $perceptionPct = ($employeeId * $m) % (self::PERCEPTION_PCT + 1);
            $extraHoursPct = ($employeeId + $m * 3) % (self::EXTRA_HOURS_PCT + 1);

            $perceptions = (int) round($baseSalary * $perceptionPct / 100);
            $extraHours  = (int) round($baseSalary * $extraHoursPct / 100);
            $total       = $baseSalary + $perceptions + $extraHours;

            $totalEarned += $total;

            $items[] = [
                'month'       => $spanishMonths[$m] . " $year",
                'base_salary' => $baseSalary,
                'perceptions' => $perceptions,
                'extra_hours' => $extraHours,
                'total'       => $total,
            ];
        }

        return [$totalEarned, $items];
    }
}
