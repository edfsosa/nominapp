<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeePerceptionSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->get();
        $perceptions = DB::table('perceptions')->where('is_active', true)->get();

        if ($employees->isEmpty() || $perceptions->isEmpty()) {
            $this->command->warn('Se necesitan empleados activos y percepciones para este seeder.');
            return;
        }

        $now = now();
        $rows = [];

        // Percepción por código
        $perceptionByCode = $perceptions->keyBy('code');

        foreach ($employees as $index => $employee) {
            // Bonificación por Desempeño: asignar al 60% de empleados
            if ($index % 5 < 3 && isset($perceptionByCode['BON001'])) {
                $rows[] = [
                    'employee_id'   => $employee->id,
                    'perception_id' => $perceptionByCode['BON001']->id,
                    'start_date'    => $now->toDateString(),
                    'end_date'      => null,
                    'custom_amount' => null,
                    'notes'         => null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            // Comisión por Ventas: solo empleados pares (simula área de ventas)
            if ($index % 2 === 0 && isset($perceptionByCode['COM001'])) {
                $rows[] = [
                    'employee_id'   => $employee->id,
                    'perception_id' => $perceptionByCode['COM001']->id,
                    'start_date'    => $now->toDateString(),
                    'end_date'      => null,
                    'custom_amount' => null,
                    'notes'         => 'Comisión estándar del departamento',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            // Gratificación Anual: asignar a todos
            if (isset($perceptionByCode['GRA001'])) {
                $rows[] = [
                    'employee_id'   => $employee->id,
                    'perception_id' => $perceptionByCode['GRA001']->id,
                    'start_date'    => $now->toDateString(),
                    'end_date'      => null,
                    'custom_amount' => null,
                    'notes'         => null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if ($rows) {
            DB::table('employee_perceptions')->insert($rows);
        }
    }
}
