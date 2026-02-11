<?php

/*
|--------------------------------------------------------------------------
| Configuracion de Nomina - Valores fijos / estructuras complejas
|--------------------------------------------------------------------------
|
| Los valores escalares editables (horas, multiplicadores, limites, etc.)
| se gestionan desde la tabla settings via App\Settings\PayrollSettings
| y son editables desde el panel de administracion.
|
| Este archivo solo contiene:
| - Estructuras complejas (tiers) definidas por ley
| - Limites de jornada definidos por ley (shift_boundaries)
| - Parametros tecnicos de procesamiento
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Limites de Jornada Diurna/Nocturna
    |--------------------------------------------------------------------------
    |
    | Horarios que definen el periodo diurno y nocturno segun la ley.
    | day_start/day_end: Periodo diurno (06:00-20:00)
    | Estos valores estan definidos por el Codigo del Trabajo y no
    | deberian ser modificados por el usuario.
    |
    */
    'shift_boundaries' => [
        'day_start' => '06:00',
        'day_end' => '20:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuracion de Vacaciones - Tiers (Ley Paraguaya)
    |--------------------------------------------------------------------------
    |
    | Segun el Codigo Laboral de Paraguay:
    | - Hasta 5 anos de antiguedad: 12 dias habiles
    | - De 5 a 10 anos: 18 dias habiles
    | - Mas de 10 anos: 30 dias habiles
    |
    | Los demas parametros de vacaciones (min_consecutive_days,
    | min_years_service) se gestionan desde PayrollSettings.
    |
    */
    'vacation' => [
        'tiers' => [
            ['min_years' => 1, 'max_years' => 5, 'days' => 12],
            ['min_years' => 5, 'max_years' => 10, 'days' => 18],
            ['min_years' => 10, 'max_years' => null, 'days' => 30],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuracion de Procesamiento
    |--------------------------------------------------------------------------
    |
    | Parametros tecnicos para el procesamiento batch de registros.
    |
    */
    'processing' => [
        'chunk_size' => env('PAYROLL_CHUNK_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuracion de Liquidacion - Tiers de Preaviso (Ley Paraguaya)
    |--------------------------------------------------------------------------
    |
    | Dias de preaviso segun antiguedad (Codigo del Trabajo Art. 87).
    | Los demas parametros (ips_rate, indemnizacion_days) se gestionan
    | desde PayrollSettings.
    |
    */
    'liquidacion' => [
        'preaviso_tiers' => [
            ['min_years' => 0, 'max_years' => 1, 'days' => 30],
            ['min_years' => 1, 'max_years' => 5, 'days' => 45],
            ['min_years' => 5, 'max_years' => 10, 'days' => 60],
            ['min_years' => 10, 'max_years' => null, 'days' => 90],
        ],
    ],
];
