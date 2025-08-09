<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeDeductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $employees = DB::table('employees')->select('id')->get();
        if ($employees->isEmpty()) return;

        $deductionsAll = DB::table('deductions')->where('applies_to_all', true)->get();
        $deductionsOpt = DB::table('deductions')->where('applies_to_all', false)->get();

        // Fecha base para todas (ejemplo: desde el 1 del mes actual)
        $startDate = Carbon::now()->startOfMonth()->toDateString();

        // 1) Aplica deducciones "para todos"
        $rows = [];
        foreach ($employees as $emp) {
            foreach ($deductionsAll as $ded) {
                $rows[] = [
                    'employee_id'  => $emp->id,
                    'deduction_id' => $ded->id,
                    'start_date'   => $startDate,
                    'end_date'     => null,
                    // custom_amount solo si la deducción es "fixed" y querés override
                    'custom_amount' => null,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }
        if (!empty($rows)) {
            DB::table('employee_deductions')->insertOrIgnore($rows);
        }

        // 2) Deducciones opcionales a ~30% de empleados
        if ($deductionsOpt->isNotEmpty()) {
            $optionalRows = [];
            foreach ($employees as $emp) {
                if (mt_rand(1, 100) <= 30) {
                    $pick = $deductionsOpt->random(); // una al azar
                    $custom = null;
                    if ($pick->calculation === 'fixed') {
                        // ejemplo: monto personalizado fijo
                        $custom = mt_rand(50_000, 300_000);
                    }
                    $optionalRows[] = [
                        'employee_id'  => $emp->id,
                        'deduction_id' => $pick->id,
                        'start_date'   => $startDate,
                        'end_date'     => null,
                        'custom_amount' => $custom,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }
            }
            if (!empty($optionalRows)) {
                DB::table('employee_deductions')->insertOrIgnore($optionalRows);
            }
        }
    }
}
