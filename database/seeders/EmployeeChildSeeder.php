<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra hijos de empleados demo para poblar el módulo de bonificación familiar.
 *
 * Asigna hijos a los primeros cuatro empleados activos:
 *   - Empleado 0: dos hijos menores de 18 años (elegibles para bonificación IPS).
 *   - Empleado 1: un hijo menor de 18 años.
 *   - Empleado 2: un hijo mayor de 18 años (no elegible para bonificación).
 *   - Empleado 3: dos hijos, uno elegible y uno no elegible.
 */
class EmployeeChildSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->take(4)->get()->values();
        $now = now();

        if ($employees->count() < 4) {
            $this->command->warn('Se necesitan al menos 4 empleados activos para el EmployeeChildSeeder.');

            return;
        }

        $children = [
            // Empleado 0 — dos hijos elegibles
            [
                'employee_id' => $employees[0]->id,
                'first_name' => 'VALENTINA',
                'last_name' => $employees[0]->last_name,
                'birth_date' => now()->subYears(8)->subMonths(3)->toDateString(),
                'ci' => null,
                'birth_certificate_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $employees[0]->id,
                'first_name' => 'EMILIO',
                'last_name' => $employees[0]->last_name,
                'birth_date' => now()->subYears(5)->subMonths(7)->toDateString(),
                'ci' => null,
                'birth_certificate_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 1 — un hijo elegible
            [
                'employee_id' => $employees[1]->id,
                'first_name' => 'MATÍAS',
                'last_name' => $employees[1]->last_name,
                'birth_date' => now()->subYears(12)->toDateString(),
                'ci' => null,
                'birth_certificate_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 2 — un hijo mayor de 18 (no elegible para bonificación)
            [
                'employee_id' => $employees[2]->id,
                'first_name' => 'GABRIELA',
                'last_name' => $employees[2]->last_name,
                'birth_date' => now()->subYears(20)->toDateString(),
                'ci' => '6234789',
                'birth_certificate_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Empleado 3 — un hijo elegible y uno mayor de 18
            [
                'employee_id' => $employees[3]->id,
                'first_name' => 'LUCIANA',
                'last_name' => $employees[3]->last_name,
                'birth_date' => now()->subYears(10)->subMonths(2)->toDateString(),
                'ci' => null,
                'birth_certificate_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $employees[3]->id,
                'first_name' => 'FACUNDO',
                'last_name' => $employees[3]->last_name,
                'birth_date' => now()->subYears(22)->toDateString(),
                'ci' => '7890123',
                'birth_certificate_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('employee_children')->insert($children);
    }
}
