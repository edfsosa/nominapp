<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra documentos de ejemplo para empleados activos.
 *
 * A cada empleado se le asigna un subconjunto determinista de los tres
 * tipos de documento disponibles (sin rand()), lo que garantiza
 * reproducibilidad entre ejecuciones.
 *
 * Los documentos son registros de referencia: las rutas apuntan a archivos
 * de ejemplo que no existen físicamente en storage (solo datos demo).
 */
class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar documentos.');
            return;
        }

        $now = now();

        $allDocTypes = [
            ['name' => 'Contrato de Trabajo',          'slug' => 'contrato'],
            ['name' => 'Cédula de Identidad',           'slug' => 'cedula'],
            ['name' => 'Certificado de Antecedentes',   'slug' => 'antecedentes'],
        ];

        $documents = [];

        foreach ($employees as $index => $employee) {
            // Determinista: todos tienen contrato y cédula; uno de cada tres
            // también tiene antecedentes (según índice par/impar).
            $count = ($index % 3 === 0) ? 3 : 2;

            foreach (array_slice($allDocTypes, 0, $count) as $docType) {
                $documents[] = [
                    'employee_id' => $employee->id,
                    'name'        => $docType['name'],
                    'file_path'   => "documents/{$employee->ci}/{$docType['slug']}.pdf",
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
