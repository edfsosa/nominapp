<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra empleados demo con nombres paraguayos realistas.
 *
 * Cada empleado se crea con Employee::create() para disparar el EmployeeObserver
 * (que asigna deducciones obligatorias automáticamente), seguido de su contrato activo.
 *
 * Salarios de referencia: Salario Mínimo PY 2025 ≈ Gs. 2,850,240.
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $userId    = DB::table('users')->value('id');
        $branches  = DB::table('branches')->pluck('id', 'name');
        $schedules = DB::table('schedules')->pluck('id', 'name');
        $positions = DB::table('positions')->pluck('id', 'name');
        $depts     = DB::table('departments')->pluck('id', 'name');

        /**
         * Cada entrada: campos del empleado + clave 'contract' con los datos del contrato.
         * Los campos 'employee_id', 'created_by_id' y 'status' del contrato
         * se inyectan automáticamente al momento de crear.
         */
        $data = [
            [
                'first_name' => 'MARÍA',
                'last_name'  => 'GONZÁLEZ',
                'ci'         => '2845671',
                'birth_date' => '1985-04-12',
                'gender'     => 'femenino',
                'phone'      => '0981123401',
                'email'      => 'mgonzalez@empresa.com',
                'branch_id'  => $branches['Sucursal Central'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2020-03-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 5500000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Gerente de RRHH'] ?? null,
                    'department_id'=> $depts['Recursos Humanos'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'CARLOS',
                'last_name'  => 'BENÍTEZ',
                'ci'         => '3912045',
                'birth_date' => '1990-08-22',
                'gender'     => 'masculino',
                'phone'      => '0985234502',
                'email'      => 'cbenitez@empresa.com',
                'branch_id'  => $branches['Sucursal Central'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2021-06-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 3800000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Desarrollador/a'] ?? null,
                    'department_id'=> $depts['Tecnología'] ?? null,
                    'work_modality'=> 'hibrido',
                ],
            ],
            [
                'first_name' => 'JUAN',
                'last_name'  => 'RAMÍREZ',
                'ci'         => '4567890',
                'birth_date' => '1992-11-30',
                'gender'     => 'masculino',
                'phone'      => '0991345603',
                'email'      => 'jramirez@empresa.com',
                'branch_id'  => $branches['Sucursal Este'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2022-01-15',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 3000000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Ejecutivo/a de Ventas'] ?? null,
                    'department_id'=> $depts['Ventas'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'SANDRA',
                'last_name'  => 'LÓPEZ',
                'ci'         => '1987632',
                'birth_date' => '1983-02-18',
                'gender'     => 'femenino',
                'phone'      => '0972456704',
                'email'      => 'slopez@empresa.com',
                'branch_id'  => $branches['Sucursal Central'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2019-08-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 4200000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Contador/a'] ?? null,
                    'department_id'=> $depts['Finanzas'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'MIGUEL',
                'last_name'  => 'FERNÁNDEZ',
                'ci'         => '5234789',
                'birth_date' => '1988-07-05',
                'gender'     => 'masculino',
                'phone'      => '0961567805',
                'email'      => 'mfernandez@empresa.com',
                'branch_id'  => $branches['Sucursal Norte'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2021-11-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 3200000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Coordinador/a Logístico'] ?? null,
                    'department_id'=> $depts['Logística'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'ANA',
                'last_name'  => 'RODRÍGUEZ',
                'ci'         => '6345012',
                'birth_date' => '1995-03-27',
                'gender'     => 'femenino',
                'phone'      => '0983678906',
                'email'      => 'arodriguez@empresa.com',
                'branch_id'  => $branches['Sucursal Sur'] ?? null,
                'schedule_id'=> $schedules['Tarde'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2023-03-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 2900000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Representante de Atención'] ?? null,
                    'department_id'=> $depts['Atención al Cliente'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'ROBERTO',
                'last_name'  => 'VERA',
                'ci'         => '7456123',
                'birth_date' => '1987-09-14',
                'gender'     => 'masculino',
                'phone'      => '0994789007',
                'email'      => null,
                'branch_id'  => $branches['Sucursal Norte'] ?? null,
                'schedule_id'=> $schedules['Mañana'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2022-07-01',
                    'trial_days'   => null,
                    'salary_type'  => 'jornal',
                    'salary'       => 100000,   // jornal diario ≈ SMD PY 2025
                    'payroll_type' => 'weekly',
                    'position_id'  => $positions['Operario/a de Producción'] ?? null,
                    'department_id'=> $depts['Producción'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'PATRICIA',
                'last_name'  => 'GARCÍA',
                'ci'         => '8123456',
                'birth_date' => '1993-12-09',
                'gender'     => 'femenino',
                'phone'      => '0971890108',
                'email'      => 'pgarcia@empresa.com',
                'branch_id'  => $branches['Sucursal Central'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2023-06-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 2900000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Asistente Administrativo'] ?? null,
                    'department_id'=> $depts['Administración'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'DIEGO',
                'last_name'  => 'AYALA',
                'ci'         => '4789234',
                'birth_date' => '1991-05-21',
                'gender'     => 'masculino',
                'phone'      => '0986901209',
                'email'      => 'dayala@empresa.com',
                'branch_id'  => $branches['Sucursal Este'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2022-04-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 3100000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Soporte IT'] ?? null,
                    'department_id'=> $depts['Tecnología'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'CLAUDIA',
                'last_name'  => 'NÚÑEZ',
                'ci'         => '3678901',
                'birth_date' => '1989-01-16',
                'gender'     => 'femenino',
                'phone'      => '0992012310',
                'email'      => 'cnunez@empresa.com',
                'branch_id'  => $branches['Sucursal Central'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2021-09-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 3500000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Especialista en Marketing'] ?? null,
                    'department_id'=> $depts['Marketing'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'FERNANDO',
                'last_name'  => 'CABRAL',
                'ci'         => '2901234',
                'birth_date' => '1980-06-03',
                'gender'     => 'masculino',
                'phone'      => '0973123411',
                'email'      => 'fcabral@empresa.com',
                'branch_id'  => $branches['Sucursal Sur'] ?? null,
                'schedule_id'=> $schedules['Estándar'] ?? null,
                'status'     => 'active',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2020-01-15',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 4800000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Supervisor/a de Ventas'] ?? null,
                    'department_id'=> $depts['Ventas'] ?? null,
                    'work_modality'=> 'presencial',
                ],
            ],
            [
                'first_name' => 'ROSA',
                'last_name'  => 'MARTÍNEZ',
                'ci'         => '5890123',
                'birth_date' => '1986-10-25',
                'gender'     => 'femenino',
                'phone'      => '0984234512',
                'email'      => 'rmartinez@empresa.com',
                'branch_id'  => $branches['Sucursal Norte'] ?? null,
                'schedule_id'=> $schedules['Mañana'] ?? null,
                'status'     => 'inactive',
                'contract'   => [
                    'type'         => 'indefinido',
                    'start_date'   => '2020-05-01',
                    'trial_days'   => 30,
                    'salary_type'  => 'mensual',
                    'salary'       => 3000000,
                    'payroll_type' => 'monthly',
                    'position_id'  => $positions['Inspector/a de Calidad'] ?? null,
                    'department_id'=> $depts['Calidad'] ?? null,
                    'work_modality'=> 'presencial',
                    'status'       => 'terminated', // contrato rescindido
                ],
            ],
        ];

        foreach ($data as $row) {
            $contractData = $row['contract'];
            unset($row['contract']);

            // Employee::create() dispara EmployeeObserver::created()
            // que asigna automáticamente las deducciones obligatorias (IPS)
            $employee = Employee::create($row);

            Contract::create(array_merge([
                'employee_id'   => $employee->id,
                'status'        => 'active',
                'created_by_id' => $userId,
            ], $contractData));
        }
    }
}
