<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra permisos y licencias de ejemplo para empleados activos.
 *
 * Los permisos de maternidad se asignan solo a empleadas femeninas
 * y los de paternidad solo a empleados masculinos.
 * Las fechas son fijas (no aleatorias) para garantizar reproducibilidad.
 */
class EmployeeLeaveSeeder extends Seeder
{
    public function run(): void
    {
        $active  = Employee::where('status', 'active')->get();
        $female  = $active->where('gender', 'femenino')->values();
        $male    = $active->where('gender', 'masculino')->values();

        if ($active->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar permisos.');
            return;
        }

        $now  = now();
        $year = (int) date('Y');

        // Permisos genéricos (cualquier género), usando índices para distribuir entre activos
        $genericLeaves = [
            [
                'employee' => $active->get(0),
                'type'     => 'medical_leave',
                'reason'   => 'Consulta médica programada',
                'start'    => Carbon::create($year, 2, 10),
                'days'     => 2,
                'status'   => 'approved',
            ],
            [
                'employee' => $active->get(1),
                'type'     => 'day_off',
                'reason'   => 'Trámite personal (renovación de documentos)',
                'start'    => Carbon::create($year, 3, 5),
                'days'     => 1,
                'status'   => 'approved',
            ],
            [
                'employee' => $active->get(2),
                'type'     => 'medical_leave',
                'reason'   => 'Reposo médico post-operatorio',
                'start'    => Carbon::create($year, 1, 15),
                'days'     => 10,
                'status'   => 'approved',
            ],
            [
                'employee' => $active->get(3),
                'type'     => 'unpaid_leave',
                'reason'   => 'Viaje familiar — sin goce de sueldo',
                'start'    => Carbon::create($year, 4, 1),
                'days'     => 7,
                'status'   => 'approved',
            ],
            [
                'employee' => $active->get(4),
                'type'     => 'day_off',
                'reason'   => 'Asuntos personales',
                'start'    => Carbon::create($year, 3, 20),
                'days'     => 1,
                'status'   => 'pending',
            ],
            [
                'employee' => $active->get(5),
                'type'     => 'medical_leave',
                'reason'   => 'Consulta de especialista',
                'start'    => Carbon::create($year, 2, 25),
                'days'     => 1,
                'status'   => 'rejected',
            ],
        ];

        // Licencia de maternidad — solo empleada femenina (Ley 5508/15: 18 semanas = 126 días)
        $maternityLeave = $female->isNotEmpty() ? [
            'employee' => $female->get(0),
            'type'     => 'maternity_leave',
            'reason'   => 'Licencia por maternidad (Ley 5508/15 — 18 semanas)',
            'start'    => Carbon::create($year - 1, 9, 1),
            'days'     => 126,
            'status'   => 'approved',
        ] : null;

        // Licencia de paternidad — solo empleado masculino (Ley 5508/15: 2 semanas = 14 días)
        $paternityLeave = $male->isNotEmpty() ? [
            'employee' => $male->get(0),
            'type'     => 'paternity_leave',
            'reason'   => 'Licencia por paternidad (Ley 5508/15 — 2 semanas)',
            'start'    => Carbon::create($year - 1, 10, 15),
            'days'     => 14,
            'status'   => 'approved',
        ] : null;

        $all = array_filter(
            array_merge($genericLeaves, [$maternityLeave, $paternityLeave])
        );

        $rows = [];
        foreach ($all as $leave) {
            if (! $leave['employee']) {
                continue;
            }

            $rows[] = [
                'employee_id' => $leave['employee']->id,
                'type'        => $leave['type'],
                'start_date'  => $leave['start']->toDateString(),
                'end_date'    => $leave['start']->copy()->addDays($leave['days'] - 1)->toDateString(),
                'reason'      => $leave['reason'],
                'status'      => $leave['status'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if ($rows) {
            DB::transaction(fn() => DB::table('employee_leaves')->insert($rows));
        }
    }
}
