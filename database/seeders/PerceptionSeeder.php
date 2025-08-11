<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerceptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $perceptions = [
            [
                'name' => 'Bonificación por Desempeño',
                'description' => 'Bono mensual por cumplimiento de objetivos',
                'calculation' => 'fixed',
                'amount' => 100000.00,
                'percent' => null,
                'is_taxable' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Comisión por Ventas',
                'description' => 'Porcentaje sobre el total de ventas generadas',
                'calculation' => 'percentage',
                'amount' => null,
                'percent' => 5.00,
                'is_taxable' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Gratificación Anual',
                'description' => 'Bono anual equivalente a un porcentaje del salario',
                'calculation' => 'percentage',
                'amount' => null,
                'percent' => 30.00,
                'is_taxable' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('perceptions')->insert($perceptions);
    }
}
