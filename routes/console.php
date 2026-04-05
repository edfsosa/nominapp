<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Comando de ejemplo para mostrar frases inspiradoras
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Calcular asistencias del día
 * Se ejecuta todos los días a las 23:00 (hora Paraguay)
 * Calcula horas trabajadas, descansos, tardanzas, etc.
 */
Schedule::command('app:calculate-attendance')
    ->dailyAt('23:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Cálculo automático de asistencias completado con éxito');
    })
    ->onFailure(function () {
        Log::error('Falló el cálculo automático de asistencias');
    });

/**
 * Verificar y generar registros de ausencias faltantes
 * Se ejecuta cada 15 minutos durante horario laboral (6am - 8pm)
 * Solo días laborables: Lunes a Sabado
 */
Schedule::command('attendance:check-missing')
    ->everyFifteenMinutes()
    ->between('06:00', '20:00')
    ->days([1, 2, 3, 4, 5, 6]) // 1 = Monday, 6 = Saturday
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Verificación de ausencias completada con éxito');
    })
    ->onFailure(function () {
        Log::error('Falló la verificación de ausencias');
    });

/**
 * Limpiar registros de fallos de marcación con más de 30 días
 * Se ejecuta diariamente a las 02:00 para evitar crecimiento indefinido de la tabla
 */
Schedule::call(function () {
    $deleted = \App\Models\AttendanceMarkFailure::where('occurred_at', '<', now()->subDays(30))->delete();
    if ($deleted > 0) {
        Log::info("Limpieza de fallos de marcación: {$deleted} registros eliminados");
    }
})->dailyAt('02:00')->name('cleanup-mark-failures')->withoutOverlapping();

/**
 * Generar adelantos automáticos
 * Se ejecuta el 1° de cada mes a las 07:00 para crear y activar los adelantos
 * de empleados con advance_percent definido en su contrato activo.
 */
Schedule::command('advances:auto-generate')
    ->monthlyOn(1, '07:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Generación automática de adelantos completada');
    })
    ->onFailure(function () {
        Log::error('Falló la generación automática de adelantos');
    });

/**
 * Detectar préstamos/adelantos en mora
 * Se ejecuta diariamente a las 08:00 para marcar como 'defaulted' los préstamos
 * activos con cuotas vencidas hace más de 30 días.
 */
Schedule::command('loans:check-defaulted')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Verificación de préstamos en mora completada');
    })
    ->onFailure(function () {
        Log::error('Falló la verificación de préstamos en mora');
    });

/**
 * Expirar enrollments faciales vencidos
 * Se ejecuta cada hora para marcar como 'expired' los registros
 * en estado pending_capture cuyo expires_at ya pasó
 */
Schedule::command('face:expire-enrollments')
    ->hourly()
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Expiración automática de enrollments faciales completada');
    })
    ->onFailure(function () {
        Log::error('Falló la expiración automática de enrollments faciales');
    });
