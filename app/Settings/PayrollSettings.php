<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PayrollSettings extends Settings
{
    // Horas de trabajo por tipo de jornada
    public int $monthly_hours;
    public int $monthly_hours_nocturno;
    public float $monthly_hours_mixto;
    public int $daily_hours;
    public int $daily_hours_nocturno;
    public float $daily_hours_mixto;
    public int $days_per_month;

    // Multiplicadores de horas extra
    public float $overtime_multiplier_diurno;
    public float $overtime_multiplier_nocturno;
    public float $overtime_multiplier_holiday;

    // Límites de horas extra
    public int $overtime_max_daily_hours;

    // Liquidación
    public float $ips_employee_rate;
    public int $indemnizacion_days_per_year;

    // Vacaciones
    public int $vacation_min_consecutive_days;
    public int $vacation_min_years_service;

    public static function group(): string
    {
        return 'payroll';
    }
}
