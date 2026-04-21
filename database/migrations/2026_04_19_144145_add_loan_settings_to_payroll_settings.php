<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta los settings de préstamos en la tabla de configuración de nómina.
 *
 *   - loan_installment_cap_percent : % máximo del salario que puede representar la cuota mensual
 *   - loan_max_installments        : cantidad máxima de cuotas permitidas al crear un préstamo
 *   - loan_max_interest_rate       : tasa de interés anual máxima permitida (%)
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['name' => 'loan_installment_cap_percent', 'payload' => '25'],
            ['name' => 'loan_max_installments',        'payload' => '60'],
            ['name' => 'loan_max_interest_rate',       'payload' => '100'],
        ];

        foreach ($rows as $row) {
            DB::table('settings')
                ->where('group', 'payroll')
                ->where('name', $row['name'])
                ->exists() || DB::table('settings')->insert([
                    'group' => 'payroll',
                    'name' => $row['name'],
                    'payload' => $row['payload'],
                    'locked' => false,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'payroll')
            ->whereIn('name', ['loan_installment_cap_percent', 'loan_max_installments', 'loan_max_interest_rate'])
            ->delete();
    }
};
