<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1) Departamentos con timestamps
        $now = now();
        $departments = [
            ['name' => 'Recursos Humanos',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Finanzas',           'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tecnología',         'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Marketing',          'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ventas',             'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Atención al Cliente', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Logística',          'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Administración',     'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Producción',         'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Calidad',            'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('departments')->insert($departments);

        // 2) Mapear (name => id)
        $dept = DB::table('departments')->pluck('id', 'name');

        // 3) Posiciones por departamento
        $positions = [
            // Recursos Humanos
            ['name' => 'Gerente de RRHH',           'department_id' => $dept['Recursos Humanos'] ?? null],
            ['name' => 'Analista de RRHH',          'department_id' => $dept['Recursos Humanos'] ?? null],

            // Finanzas
            ['name' => 'Contador/a',                'department_id' => $dept['Finanzas'] ?? null],
            ['name' => 'Auxiliar Contable',         'department_id' => $dept['Finanzas'] ?? null],

            // Tecnología
            ['name' => 'Desarrollador/a',           'department_id' => $dept['Tecnología'] ?? null],
            ['name' => 'Soporte IT',                'department_id' => $dept['Tecnología'] ?? null],
            ['name' => 'Administrador/a de Sistemas', 'department_id' => $dept['Tecnología'] ?? null],

            // Marketing
            ['name' => 'Especialista en Marketing', 'department_id' => $dept['Marketing'] ?? null],
            ['name' => 'Diseñador/a Gráfico/a',     'department_id' => $dept['Marketing'] ?? null],

            // Ventas
            ['name' => 'Ejecutivo/a de Ventas',     'department_id' => $dept['Ventas'] ?? null],
            ['name' => 'Supervisor/a de Ventas',    'department_id' => $dept['Ventas'] ?? null],

            // Atención al Cliente
            ['name' => 'Representante de Atención', 'department_id' => $dept['Atención al Cliente'] ?? null],
            ['name' => 'Líder de CAC',              'department_id' => $dept['Atención al Cliente'] ?? null],

            // Logística
            ['name' => 'Coordinador/a Logístico',   'department_id' => $dept['Logística'] ?? null],
            ['name' => 'Encargado/a de Depósito',   'department_id' => $dept['Logística'] ?? null],

            // Administración
            ['name' => 'Administrador/a',           'department_id' => $dept['Administración'] ?? null],
            ['name' => 'Asistente Administrativo',  'department_id' => $dept['Administración'] ?? null],

            // Producción
            ['name' => 'Operario/a de Producción',  'department_id' => $dept['Producción'] ?? null],
            ['name' => 'Jefe/a de Producción',      'department_id' => $dept['Producción'] ?? null],

            // Calidad
            ['name' => 'Inspector/a de Calidad',    'department_id' => $dept['Calidad'] ?? null],
            ['name' => 'Jefe/a de Calidad',         'department_id' => $dept['Calidad'] ?? null],
        ];

        DB::table('positions')->insert(
            collect($positions)->map(fn($p) => array_merge($p, [
                'created_at' => $now,
                'updated_at' => $now,
            ]))->toArray()
        );
    }
}
