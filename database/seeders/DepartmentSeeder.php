<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Siembra departamentos y cargos demo asociados a la primera empresa existente. */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now       = now();
            $companyId = DB::table('companies')->value('id');

            $departments = [
                ['company_id' => $companyId, 'name' => 'Recursos Humanos',    'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Finanzas',            'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Tecnología',          'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Marketing',           'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Ventas',              'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Atención al Cliente', 'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Logística',           'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Administración',      'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Producción',          'created_at' => $now, 'updated_at' => $now],
                ['company_id' => $companyId, 'name' => 'Calidad',             'created_at' => $now, 'updated_at' => $now],
            ];

            DB::table('departments')->insert($departments);

            // Acotar por company_id para evitar colisiones si hubiera departamentos de otra empresa
            $dept = DB::table('departments')
                ->where('company_id', $companyId)
                ->pluck('id', 'name');

            $positions = [
                // Recursos Humanos
                ['name' => 'Gerente de RRHH',             'department_id' => $dept['Recursos Humanos']],
                ['name' => 'Analista de RRHH',            'department_id' => $dept['Recursos Humanos']],

                // Finanzas
                ['name' => 'Contador/a',                  'department_id' => $dept['Finanzas']],
                ['name' => 'Auxiliar Contable',           'department_id' => $dept['Finanzas']],

                // Tecnología
                ['name' => 'Desarrollador/a',             'department_id' => $dept['Tecnología']],
                ['name' => 'Soporte IT',                  'department_id' => $dept['Tecnología']],
                ['name' => 'Administrador/a de Sistemas', 'department_id' => $dept['Tecnología']],

                // Marketing
                ['name' => 'Especialista en Marketing',   'department_id' => $dept['Marketing']],
                ['name' => 'Diseñador/a Gráfico/a',       'department_id' => $dept['Marketing']],

                // Ventas
                ['name' => 'Ejecutivo/a de Ventas',       'department_id' => $dept['Ventas']],
                ['name' => 'Supervisor/a de Ventas',      'department_id' => $dept['Ventas']],

                // Atención al Cliente
                ['name' => 'Representante de Atención',   'department_id' => $dept['Atención al Cliente']],
                ['name' => 'Líder de CAC',                'department_id' => $dept['Atención al Cliente']],

                // Logística
                ['name' => 'Coordinador/a Logístico',     'department_id' => $dept['Logística']],
                ['name' => 'Encargado/a de Depósito',     'department_id' => $dept['Logística']],

                // Administración
                ['name' => 'Administrador/a',             'department_id' => $dept['Administración']],
                ['name' => 'Asistente Administrativo',    'department_id' => $dept['Administración']],

                // Producción
                ['name' => 'Operario/a de Producción',    'department_id' => $dept['Producción']],
                ['name' => 'Jefe/a de Producción',        'department_id' => $dept['Producción']],

                // Calidad
                ['name' => 'Inspector/a de Calidad',      'department_id' => $dept['Calidad']],
                ['name' => 'Jefe/a de Calidad',           'department_id' => $dept['Calidad']],
            ];

            DB::table('positions')->insert(
                collect($positions)->map(fn($p) => array_merge($p, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]))->toArray()
            );
        });
    }
}
