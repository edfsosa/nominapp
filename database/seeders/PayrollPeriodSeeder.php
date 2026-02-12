<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
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

    private function addMonthlyPeriods(array &$rows, $now): void
    {
        $start = Carbon::create(date('Y'), 1, 1);

        for ($i = 0; $i < 24; $i++) {
            $periodStart = $start->copy()->addMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $rows[] = [
                'frequency'  => 'monthly',
                'start_date' => $periodStart->toDateString(),
                'end_date'   => $periodEnd->toDateString(),
                'name'       => $periodStart->format('F Y'),
                'status'     => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    private function addBiweeklyPeriods(array &$rows, $now): void
    {
        $start = Carbon::create(date('Y'), 1, 1);

        for ($i = 0; $i < 24; $i++) {
            $month = $start->copy()->addMonths($i);

            // Primera quincena: 1-15
            $firstStart = $month->copy()->startOfMonth();
            $firstEnd = $firstStart->copy()->addDays(14);

            $rows[] = [
                'frequency'  => 'biweekly',
                'start_date' => $firstStart->toDateString(),
                'end_date'   => $firstEnd->toDateString(),
                'name'       => '1ra Quincena ' . $month->format('F Y'),
                'status'     => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Segunda quincena: 16-fin de mes
            $secondStart = $firstEnd->copy()->addDay();
            $secondEnd = $month->copy()->endOfMonth();

            $rows[] = [
                'frequency'  => 'biweekly',
                'start_date' => $secondStart->toDateString(),
                'end_date'   => $secondEnd->toDateString(),
                'name'       => '2da Quincena ' . $month->format('F Y'),
                'status'     => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    private function addWeeklyPeriods(array &$rows, $now): void
    {
        $start = Carbon::create(date('Y'), 1, 1)->startOfWeek();

        for ($i = 0; $i < 104; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();

            $rows[] = [
                'frequency'  => 'weekly',
                'start_date' => $weekStart->toDateString(),
                'end_date'   => $weekEnd->toDateString(),
                'name'       => 'Semana ' . $weekStart->format('W/Y'),
                'status'     => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }
}
