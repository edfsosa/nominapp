<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::all();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados para generar documentos.');
            return;
        }

        $now = now();
        $documents = [];

        $documentTypes = [
            ['name' => 'Contrato de Trabajo',      'path' => 'documents/{ci}/contrato.pdf'],
            ['name' => 'Cédula de Identidad',       'path' => 'documents/{ci}/cedula.pdf'],
            ['name' => 'Certificado de Antecedentes', 'path' => 'documents/{ci}/antecedentes.pdf'],
        ];

        foreach ($employees as $employee) {
            // Cada empleado tiene al menos contrato y cédula
            $typesToAssign = array_slice($documentTypes, 0, rand(2, count($documentTypes)));

            foreach ($typesToAssign as $docType) {
                $documents[] = [
                    'employee_id' => $employee->id,
                    'name'        => $docType['name'],
                    'file_path'   => str_replace('{ci}', $employee->ci, $docType['path']),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        foreach (array_chunk($documents, 500) as $chunk) {
            DB::table('documents')->insert($chunk);
        }
    }
}
