<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuracion de Horas de Trabajo
    |--------------------------------------------------------------------------
    |
    | Estos valores se usan para calcular la tarifa horaria y diaria
    | del empleado a partir de su salario base mensual.
    |
    | monthly: Horas laborales mensuales (default: 240 = 30 dias * 8 horas)
    | daily: Horas por jornada diaria (default: 8 horas)
    | days_per_month: Dias laborales por mes (default: 30 dias)
    |
    */
    'hours' => [
        'monthly' => env('PAYROLL_MONTHLY_HOURS', 240),
        'daily' => env('PAYROLL_DAILY_HOURS', 8),
        'days_per_month' => env('PAYROLL_DAYS_PER_MONTH', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multiplicadores de Horas Extra
    |--------------------------------------------------------------------------
    |
    | Factores de multiplicacion para el calculo de horas extra segun
    | el tipo de dia trabajado.
    |
    | holiday_weekend: Multiplicador para feriados y fines de semana (default: 2.0)
    | regular: Multiplicador para dias normales (default: 1.5)
    |
    */
    'overtime_multipliers' => [
        'holiday_weekend' => env('PAYROLL_OT_HOLIDAY_MULTIPLIER', 2.0),
        'regular' => env('PAYROLL_OT_REGULAR_MULTIPLIER', 1.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuracion de Vacaciones (Ley Paraguaya)
    |--------------------------------------------------------------------------
    |
    | Segun el Codigo Laboral de Paraguay:
    | - Hasta 5 anos de antiguedad: 12 dias habiles
    | - De 5 a 10 anos: 18 dias habiles
    | - Mas de 10 anos: 30 dias habiles
    |
    | El fraccionamiento minimo es de 6 dias consecutivos.
    | Se requiere minimo 1 ano de servicio para tener derecho a vacaciones.
    |
    */
    'vacation' => [
        'tiers' => [
            ['min_years' => 1, 'max_years' => 5, 'days' => 12],
            ['min_years' => 5, 'max_years' => 10, 'days' => 18],
            ['min_years' => 10, 'max_years' => null, 'days' => 30],
        ],
        'minimum_consecutive_days' => env('VACATION_MIN_CONSECUTIVE_DAYS', 6),
        'minimum_years_service' => env('VACATION_MIN_YEARS_SERVICE', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuracion de Procesamiento
    |--------------------------------------------------------------------------
    |
    | Parametros para el procesamiento batch de registros.
    |
    | chunk_size: Cantidad de registros a procesar por lote (default: 100)
    |
    */
    'processing' => [
        'chunk_size' => env('PAYROLL_CHUNK_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuracion de Liquidacion / Finiquito
    |--------------------------------------------------------------------------
    |
    | Parametros para el calculo de liquidaciones segun el Codigo
    | Laboral de Paraguay (Art. 78-100).
    |
    | preaviso_tiers: Dias de preaviso segun antiguedad
    | indemnizacion_days_per_year: 15 dias por ano de servicio
    | ips_employee_rate: Porcentaje de aporte IPS obrero (9%)
    |
    */
    'liquidacion' => [
        'ips_employee_rate' => env('LIQUIDACION_IPS_RATE', 9),
        'preaviso_tiers' => [
            ['min_years' => 0, 'max_years' => 1, 'days' => 30],
            ['min_years' => 1, 'max_years' => 5, 'days' => 45],
            ['min_years' => 5, 'max_years' => 10, 'days' => 60],
            ['min_years' => 10, 'max_years' => null, 'days' => 90],
        ],
        'indemnizacion_days_per_year' => 15,
    ],
];
