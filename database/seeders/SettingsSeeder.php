<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra los valores iniciales de configuración del sistema.
 *
 * Cubre los dos grupos de ajustes editables desde el panel:
 *   - GeneralSettings (group: "general") — datos de empresa, zona horaria, préstamos, contratos
 *   - PayrollSettings (group: "payroll") — jornadas, multiplicadores HE, IPS, vacaciones
 *
 * Los valores reflejan la legislación paraguaya vigente (Código Laboral — Ley 213/93).
 *
 * spatie/laravel-settings almacena cada propiedad como una fila independiente
 * con payload JSON. Se usa updateOrInsert para idempotencia.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGeneralSettings();
        $this->seedPayrollSettings();
    }

    /**
     * Siembra los ajustes generales de la empresa demo.
     *
     * Los datos de empresa (nombre, RUC, etc.) son coherentes con CompanySeeder.
     */
    private function seedGeneralSettings(): void
    {
        $this->upsertGroup('general', [
            // Datos de empresa
            'company_name'            => 'Empresa Demo S.A.',
            'company_logo'            => null,
            'company_ruc'             => '80012345-6',
            'company_employer_number' => '12345',
            'company_address'         => 'Av. Mariscal López 1234, Asunción',
            'company_phone'           => '0211234567',
            'company_email'           => 'info@empresademo.com.py',
            'company_city'            => 'Asunción',

            // Configuración laboral
            'timezone'               => 'America/Asuncion',
            'working_hours_per_week' => 48,   // Ley PY: 48 hrs/semana (Art. 194)

            // Préstamos
            'max_loan_amount'        => 5000000,  // Gs. 5.000.000

            // Contratos
            'contract_alert_days'    => 30,

            // Registro facial
            'face_enrollment_expiry_hours' => 48,
        ]);

        $this->command->info('GeneralSettings sembrados.');
    }

    /**
     * Siembra los parámetros de nómina según el Código Laboral paraguayo (Ley 213/93).
     *
     * Jornadas (Arts. 194–197):
     *   - Diurna:   8 hrs/día  × 30 días = 240 hrs/mes
     *   - Nocturna: 7 hrs/día  × 30 días = 210 hrs/mes
     *   - Mixta:    7.5 hrs/día × 30 días = 225 hrs/mes
     *
     * Horas extra (Art. 234): diurnas +50% → 1.5x | nocturnas 2.6x | feriado 2.0x
     * Límite HE (Art. 202): máximo 3 hrs extra por día.
     */
    private function seedPayrollSettings(): void
    {
        $this->upsertGroup('payroll', [
            // Jornada diurna (Art. 194)
            'monthly_hours'  => 240,
            'daily_hours'    => 8,
            'days_per_month' => 30,

            // Jornada nocturna (Art. 196)
            'monthly_hours_nocturno' => 210,
            'daily_hours_nocturno'   => 7,

            // Jornada mixta (Art. 197)
            'monthly_hours_mixto' => 225.0,
            'daily_hours_mixto'   => 7.5,

            // Multiplicadores de horas extra (Art. 234)
            'overtime_multiplier_diurno'   => 1.5,
            'overtime_multiplier_nocturno' => 2.6,
            'overtime_multiplier_holiday'  => 2.0,

            // Límites HE (Art. 202)
            'overtime_max_daily_hours' => 3,

            // IPS y liquidación
            'ips_employee_rate'          => 9.0,
            'ips_deduction_code'         => 'IPS001',
            'indemnizacion_days_per_year' => 15,

            // Vacaciones
            'vacation_min_consecutive_days' => 6,
            'vacation_min_years_service'    => 1,
            'vacation_business_days'        => [1, 2, 3, 4, 5, 6], // Lun–Sáb
        ]);

        $this->command->info('PayrollSettings sembrados.');
    }

    /**
     * Inserta o actualiza todas las propiedades de un grupo de settings.
     *
     * Cada propiedad se almacena como fila individual con payload JSON,
     * según el formato interno de spatie/laravel-settings v2.
     *
     * @param  string                      $group      Nombre del grupo (e.g., 'general')
     * @param  array<string, mixed>        $properties Mapa nombre → valor
     */
    private function upsertGroup(string $group, array $properties): void
    {
        $now = now();

        foreach ($properties as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => $group, 'name' => $name],
                [
                    'payload'    => json_encode($value),
                    'locked'     => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
