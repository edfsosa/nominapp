<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta los settings de adelantos en la tabla de configuración de nómina.
 *
 * También completa los settings que faltaban en la BD:
 *   - overtime_max_weekly_hours
 *   - irp_annual_threshold
 *   - irp_rate
 *
 * Nuevos settings de adelantos:
 *   - advance_max_percent     : % máximo del salario por adelanto (default: 50)
 *   - advance_max_per_period  : máximo de adelantos por período (0 = sin límite)
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // Settings faltantes en la BD (ya declarados en PayrollSettings)
            ['name' => 'overtime_max_weekly_hours', 'payload' => '9'],
            ['name' => 'irp_annual_threshold',      'payload' => '80000000'],
            ['name' => 'irp_rate',                  'payload' => '10'],

            // Nuevos settings de adelantos
            ['name' => 'advance_max_percent',    'payload' => '50'],
            ['name' => 'advance_max_per_period', 'payload' => '0'],
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
            ->whereIn('name', ['advance_max_percent', 'advance_max_per_period'])
            ->delete();
    }
};
