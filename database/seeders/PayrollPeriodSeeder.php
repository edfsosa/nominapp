<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra períodos de nómina para el año en curso y el siguiente.
 *
 * Genera períodos mensuales, quincenales y semanales.
 * Los períodos pasados se marcan como 'closed', el período actual como
 * 'processing' y los futuros como 'draft'.
 */
class PayrollPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $now  = now();
        $rows = [];

        $this->addMonthlyPeriods($rows, $now);
        $this->addBiweeklyPeriods($rows, $now);
        $this->addWeeklyPeriods($rows, $now);

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('payroll_periods')->insert($chunk);
            }
        });
    }

    /**
     * Determina el status de un período según sus fechas.
     *
     * @param  Carbon $start Inicio del período
     * @param  Carbon $end   Fin del período
     * @return string 'closed' | 'processing' | 'draft'
     */
    private function statusFor(Carbon $start, Carbon $end): string
    {
        $today = Carbon::today();

        if ($end->lt($today)) {
            return 'closed';
        }

        if ($start->lte($today) && $end->gte($today)) {
            return 'processing';
        }

        return 'draft';
    }

    /**
     * Agrega 24 períodos mensuales a partir de enero del año en curso.
     *
     * @param  array<int, array<string, mixed>> $rows
     */
    private function addMonthlyPeriods(array &$rows, Carbon $now): void
    {
        $base = Carbon::create((int) date('Y'), 1, 1);

        for ($i = 0; $i < 24; $i++) {
            $start = $base->copy()->addMonths($i)->startOfMonth();
            $end   = $start->copy()->endOfMonth();

            $rows[] = [
                'frequency'  => 'monthly',
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
                'name'       => ucfirst($start->locale('es')->isoFormat('MMMM YYYY')),
                'status'     => $this->statusFor($start, $end),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * Agrega 48 períodos quincenales (2 por mes) a partir de enero del año en curso.
     *
     * @param  array<int, array<string, mixed>> $rows
     */
    private function addBiweeklyPeriods(array &$rows, Carbon $now): void
    {
        $base = Carbon::create((int) date('Y'), 1, 1);

        for ($i = 0; $i < 24; $i++) {
            $month = $base->copy()->addMonths($i);
            $label = ucfirst($month->locale('es')->isoFormat('MMMM YYYY'));

            // Primera quincena: 1–15
            $firstStart = $month->copy()->startOfMonth();
            $firstEnd   = $firstStart->copy()->addDays(14);

            $rows[] = [
                'frequency'  => 'biweekly',
                'start_date' => $firstStart->toDateString(),
                'end_date'   => $firstEnd->toDateString(),
                'name'       => "1ra Quincena $label",
                'status'     => $this->statusFor($firstStart, $firstEnd),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Segunda quincena: 16–fin de mes
            $secondStart = $firstEnd->copy()->addDay();
            $secondEnd   = $month->copy()->endOfMonth();

            $rows[] = [
                'frequency'  => 'biweekly',
                'start_date' => $secondStart->toDateString(),
                'end_date'   => $secondEnd->toDateString(),
                'name'       => "2da Quincena $label",
                'status'     => $this->statusFor($secondStart, $secondEnd),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * Agrega 104 períodos semanales (2 años) a partir del lunes de la primera semana del año.
     *
     * @param  array<int, array<string, mixed>> $rows
     */
    private function addWeeklyPeriods(array &$rows, Carbon $now): void
    {
        $base = Carbon::create((int) date('Y'), 1, 1)->startOfWeek();

        for ($i = 0; $i < 104; $i++) {
            $start = $base->copy()->addWeeks($i);
            $end   = $start->copy()->endOfWeek();

            $rows[] = [
                'frequency'  => 'weekly',
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
                'name'       => 'Semana ' . $start->isoWeek() . ' — ' . $start->format('d/m') . ' al ' . $end->format('d/m/Y'),
                'status'     => $this->statusFor($start, $end),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }
}
