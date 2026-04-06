<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Agrega configuraciones de nómina pendientes:
 * - Multiplicador horas extra nocturnas en feriado
 * - Salarios mínimos legales (Ministerio de Trabajo, vigentes 2026)
 * - Porcentaje de bonificación familiar (Art. 253-262 CLT)
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // Multiplicador horas extra nocturnas en día feriado (Art. 234 CLT)
        $this->migrator->add('payroll.overtime_multiplier_nocturno_holiday', 3.0);

        // Salario mínimo mensual vigente (Resolución Ministerio de Trabajo 2026)
        $this->migrator->add('payroll.min_salary_monthly', 2680373);

        // Salario mínimo diario para jornaleros vigente
        $this->migrator->add('payroll.min_salary_daily_jornal', 102706);

        // Bonificación familiar: 5% del salario mínimo por hijo (Arts. 253-262 CLT)
        $this->migrator->add('payroll.family_bonus_percentage', 5.0);
    }

    public function down(): void
    {
        $this->migrator->delete('payroll.overtime_multiplier_nocturno_holiday');
        $this->migrator->delete('payroll.min_salary_monthly');
        $this->migrator->delete('payroll.min_salary_daily_jornal');
        $this->migrator->delete('payroll.family_bonus_percentage');
    }
};
