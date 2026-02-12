<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeLeaveSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar permisos.');
            return;
        }

        DB::transaction(function () use ($employees) {
            $now = now();
            $year = (int) date('Y');
            $leaves = [];

            $leaveTemplates = [
                [
                    'type'   => 'medical_leave',
                    'reason' => 'Consulta médica programada',
                    'days'   => [1, 3],
                ],
                [
                    'type'   => 'day_off',
                    'reason' => 'Trámite personal',
                    'days'   => [1, 1],
                ],
                [
                    'type'   => 'maternity_leave',
                    'reason' => 'Licencia por maternidad (Ley 5508/15)',
                    'days'   => [126, 126],
                ],
                [
                    'type'   => 'paternity_leave',
                    'reason' => 'Licencia por paternidad',
                    'days'   => [14, 14],
                ],
                [
                    'type'   => 'medical_leave',
                    'reason' => 'Reposo médico por intervención quirúrgica',
                    'days'   => [15, 30],
                ],
                [
                    'type'   => 'unpaid_leave',
                    'reason' => 'Asuntos personales',
                    'days'   => [5, 10],
                ],
            ];

            $statuses = ['approved', 'approved', 'approved', 'pending', 'rejected'];

            foreach ($employees->take(5) as $index => $employee) {
                $template = $leaveTemplates[$index % count($leaveTemplates)];
                $daysCount = rand($template['days'][0], $template['days'][1]);
                $startDate = Carbon::create($year, rand(1, 6), rand(1, 20));
                $endDate = $startDate->copy()->addDays($daysCount - 1);

                $leaves[] = [
                    'employee_id' => $employee->id,
                    'type'        => $template['type'],
                    'start_date'  => $startDate->toDateString(),
                    'end_date'    => $endDate->toDateString(),
                    'reason'      => $template['reason'],
                    'status'      => $statuses[$index % count($statuses)],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            if ($leaves) {
                DB::table('employee_leaves')->insert($leaves);
            }
        });
    }
}
