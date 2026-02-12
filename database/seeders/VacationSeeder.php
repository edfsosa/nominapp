<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VacationSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar vacaciones.');
            return;
        }

        DB::transaction(function () use ($employees) {
            $now = now();
            $year = (int) date('Y');
            $balances = [];
            $vacations = [];

            foreach ($employees as $employee) {
                $hireDate = Carbon::parse($employee->hire_date);
                $yearsOfService = $hireDate->diffInYears(now());

                // Ley paraguaya: 12 días (0-5 años), 18 días (5-10), 30 días (10+)
                $entitledDays = match (true) {
                    $yearsOfService >= 10 => 30,
                    $yearsOfService >= 5  => 18,
                    default               => 12,
                };

                $usedDays = rand(0, min($entitledDays, 8));
                $pendingDays = rand(0, min(3, $entitledDays - $usedDays));

                $balances[] = [
                    'employee_id'      => $employee->id,
                    'year'             => $year,
                    'years_of_service' => $yearsOfService,
                    'entitled_days'    => $entitledDays,
                    'used_days'        => $usedDays,
                    'pending_days'     => $pendingDays,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            DB::table('employee_vacation_balances')->insert($balances);

            // Obtener IDs de balances insertados
            $balanceMap = DB::table('employee_vacation_balances')
                ->where('year', $year)
                ->pluck('id', 'employee_id');

            // Generar solicitudes de vacaciones para algunos empleados
            $statuses = ['approved', 'approved', 'pending', 'rejected'];

            foreach ($employees->take(6) as $index => $employee) {
                $balanceId = $balanceMap[$employee->id] ?? null;
                $startDate = Carbon::create($year, rand(1, 6), rand(1, 20));
                $endDate = $startDate->copy()->addDays(rand(3, 10));
                $returnDate = $endDate->copy()->addDay();
                $status = $statuses[$index % count($statuses)];
                $businessDays = $this->countBusinessDays($startDate, $endDate);

                $vacations[] = [
                    'employee_id'         => $employee->id,
                    'vacation_balance_id' => $balanceId,
                    'start_date'          => $startDate->toDateString(),
                    'end_date'            => $endDate->toDateString(),
                    'return_date'         => $returnDate->toDateString(),
                    'type'                => 'paid',
                    'reason'              => 'Descanso anual',
                    'status'              => $status,
                    'business_days'       => $businessDays,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }

            if ($vacations) {
                DB::table('vacations')->insert($vacations);
            }
        });
    }

    private function countBusinessDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (!$current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }
}
