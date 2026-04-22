<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta los settings de retiros de mercadería en la tabla de configuración de nómina.
 *
 *   - merchandise_max_amount      : monto máximo por retiro (Gs.)
 *   - merchandise_max_installments: cantidad máxima de cuotas permitidas
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['name' => 'merchandise_max_amount',       'payload' => '10000000'],
            ['name' => 'merchandise_max_installments', 'payload' => '24'],
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
            ->whereIn('name', ['merchandise_max_amount', 'merchandise_max_installments'])
            ->delete();
    }
};
