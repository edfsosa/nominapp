<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Horas de trabajo por tipo de jornada
        $this->migrator->add('payroll.monthly_hours', 240);
        $this->migrator->add('payroll.monthly_hours_nocturno', 210);
        $this->migrator->add('payroll.monthly_hours_mixto', 225);
        $this->migrator->add('payroll.daily_hours', 8);
        $this->migrator->add('payroll.daily_hours_nocturno', 7);
        $this->migrator->add('payroll.daily_hours_mixto', 7.5);
        $this->migrator->add('payroll.days_per_month', 30);

        // Multiplicadores de horas extra (Código del Trabajo Art. 234)
        $this->migrator->add('payroll.overtime_multiplier_diurno', 1.5);
        $this->migrator->add('payroll.overtime_multiplier_nocturno', 2.6);
        $this->migrator->add('payroll.overtime_multiplier_holiday', 2.0);

        // Límites de horas extra (Art. 202)
        $this->migrator->add('payroll.overtime_max_daily_hours', 3);

        // Liquidación
        $this->migrator->add('payroll.ips_employee_rate', 9);
        $this->migrator->add('payroll.indemnizacion_days_per_year', 15);

        // Vacaciones
        $this->migrator->add('payroll.vacation_min_consecutive_days', 6);
        $this->migrator->add('payroll.vacation_min_years_service', 1);
    }

    public function down(): void
    {
        $this->migrator->delete('payroll.monthly_hours');
        $this->migrator->delete('payroll.monthly_hours_nocturno');
        $this->migrator->delete('payroll.monthly_hours_mixto');
        $this->migrator->delete('payroll.daily_hours');
        $this->migrator->delete('payroll.daily_hours_nocturno');
        $this->migrator->delete('payroll.daily_hours_mixto');
        $this->migrator->delete('payroll.days_per_month');
        $this->migrator->delete('payroll.overtime_multiplier_diurno');
        $this->migrator->delete('payroll.overtime_multiplier_nocturno');
        $this->migrator->delete('payroll.overtime_multiplier_holiday');
        $this->migrator->delete('payroll.overtime_max_daily_hours');
        $this->migrator->delete('payroll.ips_employee_rate');
        $this->migrator->delete('payroll.indemnizacion_days_per_year');
        $this->migrator->delete('payroll.vacation_min_consecutive_days');
        $this->migrator->delete('payroll.vacation_min_years_service');
    }
};
