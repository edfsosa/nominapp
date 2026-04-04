<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Agrega el límite semanal de horas extra a la configuración de nómina.
 *
 * Art. 202 CLT: máximo 3 horas diarias y 9 horas semanales de trabajo extraordinario.
 */
class AddOvertimeWeeklyLimitToPayrollSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('payroll.overtime_max_weekly_hours', 9);
    }

    public function down(): void
    {
        $this->migrator->delete('payroll.overtime_max_weekly_hours');
    }
}
