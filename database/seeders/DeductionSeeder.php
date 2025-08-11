<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $deductions = [
            [
                'name' => 'Aporte IPS',
                'description' => 'Aporte al Instituto de Previsión Social (9% del salario)',
                'calculation' => 'percentage',
                'amount' => null, // No se usa para porcentaje
                'percent' => 9.00, // Porcentaje del salario
                'is_mandatory' => true, // Obligatorio
                'is_active' => true, // Activo por defecto
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Impuesto a la Renta Personal (IRP)',
                'description' => 'Deducción de impuesto sobre la renta según la ley vigente',
                'calculation' => 'percentage',
                'amount' => null, // No se usa para porcentaje
                'percent' => 8.00, // Porcentaje del salario
                'is_mandatory' => true, // Obligatorio
                'is_active' => true, // Activo por defecto
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Seguro Médico Privado',
                'description' => 'Prima mensual del seguro médico',
                'calculation' => 'fixed',
                'amount' => 150000.00, // Monto fijo
                'percent' => null, // No se usa para monto fijo
                'is_mandatory' => false, // No obligatorio
                'is_active' => true, // Activo por defecto
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Aporte Sindical',
                'description' => 'Aporte mensual al sindicato de trabajadores',
                'calculation' => 'fixed',
                'amount' => 50000.00, // Monto fijo
                'percent' => null, // No se usa para monto fijo
                'is_mandatory' => false, // No obligatorio
                'is_active' => true, // Activo por defecto
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('deductions')->insert($deductions);
    }
}
