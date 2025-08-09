<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeePerceptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $employees = DB::table('employees')->select('id')->get();
        if ($employees->isEmpty()) return;

        $perceptions = DB::table('perceptions')->get()->keyBy('name');

        $startDate = Carbon::now()->startOfMonth()->toDateString();

        $rows = [];

        // 1) Gratificación Anual (si existe y aplica a todos)
        if ($perceptions->has('Gratificación Anual')) {
            $grat = $perceptions['Gratificación Anual'];
            if ($grat->applies_to_all) {
                foreach ($employees as $emp) {
                    $rows[] = [
                        'employee_id'    => $emp->id,
                        'perception_id'  => $grat->id,
                        'start_date'     => $startDate,
                        'end_date'       => null,
                        'custom_amount'  => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
        }

        // 2) Horas Extras (~40%)
        if ($perceptions->has('Horas Extras')) {
            $he = $perceptions['Horas Extras'];
            foreach ($employees as $emp) {
                if (mt_rand(1, 100) <= 40) {
                    $rows[] = [
                        'employee_id'    => $emp->id,
                        'perception_id'  => $he->id,
                        'start_date'     => $startDate,
                        'end_date'       => null,
                        // Se calcula luego; aquí no seteamos override
                        'custom_amount'  => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
        }

        // 3) Bonificación por Desempeño (~20%) con custom_amount
        if ($perceptions->has('Bonificación por Desempeño')) {
            $bonus = $perceptions['Bonificación por Desempeño'];
            foreach ($employees as $emp) {
                if (mt_rand(1, 100) <= 20) {
                    $rows[] = [
                        'employee_id'    => $emp->id,
                        'perception_id'  => $bonus->id,
                        'start_date'     => $startDate,
                        'end_date'       => null,
                        'custom_amount'  => mt_rand(300_000, 800_000),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
        }

        // 4) Comisión por Ventas (~15%)
        if ($perceptions->has('Comisión por Ventas')) {
            $com = $perceptions['Comisión por Ventas'];
            foreach ($employees as $emp) {
                if (mt_rand(1, 100) <= 15) {
                    $rows[] = [
                        'employee_id'    => $emp->id,
                        'perception_id'  => $com->id,
                        'start_date'     => $startDate,
                        'end_date'       => null,
                        'custom_amount'  => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
        }

        if (!empty($rows)) {
            // Evita violar la unique (employee_id, perception_id, start_date)
            DB::table('employee_perceptions')->insertOrIgnore($rows);
        }
    }
}
