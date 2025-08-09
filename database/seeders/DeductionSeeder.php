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
                'value' => 9.00,
                'applies_to_all' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Impuesto a la Renta Personal (IRP)',
                'description' => 'Deducción de impuesto sobre la renta según la ley vigente',
                'calculation' => 'percentage',
                'value' => 10.00,
                'applies_to_all' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Seguro Médico Privado',
                'description' => 'Prima mensual del seguro médico',
                'calculation' => 'fixed',
                'value' => 250000.00,
                'applies_to_all' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Aporte Sindical',
                'description' => 'Aporte mensual al sindicato de trabajadores',
                'calculation' => 'fixed',
                'value' => 50000.00,
                'applies_to_all' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('deductions')->insert($deductions);
    }
}
