<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Siembra deducciones de ejemplo para entorno de desarrollo/demo. */
class DeductionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $deductions = [
            [
                'name'         => 'Aporte IPS',
                'code'         => 'IPS001',
                'description'  => 'Aporte al Instituto de Previsión Social (9% del salario)',
                'calculation'  => 'percentage',
                'amount'       => null,
                'percent'      => 9.00,
                'is_mandatory' => true,
                'affects_irp'  => false,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'Impuesto a la Renta Personal (IRP)',
                'code'         => 'IRP001',
                'description'  => 'Deducción de impuesto sobre la renta según la ley vigente',
                'calculation'  => 'percentage',
                'amount'       => null,
                'percent'      => 8.00,
                'is_mandatory' => true,
                'affects_irp'  => true,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'Seguro Médico Privado',
                'code'         => 'SMP001',
                'description'  => 'Prima mensual del seguro médico',
                'calculation'  => 'fixed',
                'amount'       => 150000.00,
                'percent'      => null,
                'is_mandatory' => false,
                'affects_irp'  => false,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'name'         => 'Aporte Sindical',
                'code'         => 'AS001',
                'description'  => 'Aporte mensual al sindicato de trabajadores',
                'calculation'  => 'fixed',
                'amount'       => 50000.00,
                'percent'      => null,
                'is_mandatory' => false,
                'affects_irp'  => false,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ];

        DB::table('deductions')->insert($deductions);
    }
}
