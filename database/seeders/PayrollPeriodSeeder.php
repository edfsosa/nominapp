<?php

namespace Database\Seeders;

use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PayrollPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->generateMonthlyPeriods();
        $this->generateBiweeklyPeriods();
        $this->generateWeeklyPeriods();
    }

    protected function generateMonthlyPeriods()
    {
        $start = Carbon::create(date('Y'), 1, 1);

        for ($i = 0; $i < 24; $i++) {
            $periodStart = $start->copy()->addMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            PayrollPeriod::updateOrCreate([
                'frequency' => 'monthly',
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
            ], [
                'name' => $periodStart->format('F Y'), // Ej: Enero 2025
                'status' => 'draft',
            ]);
        }
    }

    protected function generateBiweeklyPeriods()
    {
        $start = Carbon::create(date('Y'), 1, 1);

        for ($i = 0; $i < 24; $i++) {
            $month = $start->copy()->addMonths($i);

            // Primer quincena
            $firstStart = $month->copy()->startOfMonth();
            $firstEnd = $firstStart->copy()->addDays(14);

            PayrollPeriod::updateOrCreate([
                'frequency' => 'biweekly',
                'start_date' => $firstStart->toDateString(),
                'end_date' => $firstEnd->toDateString(),
            ], [
                'name' => '1ra Quincena ' . $month->format('F Y'),
                'status' => 'draft',
            ]);

            // Segunda quincena
            $secondStart = $firstEnd->copy()->addDay();
            $secondEnd = $secondStart->copy()->endOfMonth();

            PayrollPeriod::updateOrCreate([
                'frequency' => 'biweekly',
                'start_date' => $secondStart->toDateString(),
                'end_date' => $secondEnd->toDateString(),
            ], [
                'name' => '2da Quincena ' . $month->format('F Y'),
                'status' => 'draft',
            ]);
        }
    }

    protected function generateWeeklyPeriods()
    {
        $start = Carbon::create(date('Y'), 1, 1)->startOfWeek(); // Lunes

        for ($i = 0; $i < 104; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();

            PayrollPeriod::updateOrCreate([
                'frequency' => 'weekly',
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
            ], [
                'name' => 'Semana ' . $weekStart->format('W/Y'),
                'status' => 'draft',
            ]);
        }
    }
}
